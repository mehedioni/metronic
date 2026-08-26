# File storage

Everything the application stores goes through one service, so the storage
provider is a configuration choice rather than something spread across the
codebase.

```
Controller ─► module Service ─► App\Core\Services\FileStorageService ─► configured disk
                                                                          ├── local / public
                                                                          ├── S3
                                                                          ├── R2 / Spaces
                                                                          └── any Laravel disk
```

## Switching provider

```env
FILES_DISK=public     # today
FILES_DISK=s3         # later
```

Nothing else changes: no controller, no model, no Vue component, and **no
migration**. Switching to a real S3-compatible provider additionally needs the
driver package, which is not installed yet:

```bash
composer require league/flysystem-aws-s3-v3
```

`config/files.php` reads `FILES_DISK`, falling back to Laravel's own
`FILESYSTEM_DISK`, then to `public` — because an image the browser loads needs
a disk that can produce a URL.

## Where the URL comes from

For the `public` disk it is root-relative — `/storage/products/…` — so the host
that served the page serves the images. The same install works on
`metronic.test`, `localhost` or a tunnel without anything being kept in step.

Laravel's default builds that URL from `APP_URL`, which is why a stale
`APP_URL=http://localhost` produced `http://localhost/storage/…` on a site
running at `http://metronic.test`. `config/filesystems.php` no longer reads
`APP_URL` for this. (`APP_URL` still matters elsewhere — mail links, signed
URLs, queued jobs — so keep it correct regardless.)

Set `FILES_PUBLIC_URL` when links must be absolute: a CDN in front of storage,
or images that appear in email.

```env
FILES_PUBLIC_URL=https://cdn.example.com/storage
```

An S3-style disk is unaffected — its URL comes from the provider's own
configuration and is absolute by nature.

## The service

`App\Core\Services\FileStorageService`

| Method | Purpose |
| --- | --- |
| `store($file, $path, $options)` | Save an upload; returns a `StoredFile` |
| `put($contents, $path, $filename)` | Save raw contents (an export, a PDF) |
| `url($path, $disk = null)` | The URL to load it, or `null` if the disk serves none |
| `temporaryUrl($path, $minutes)` | Expiring URL, for a private disk |
| `delete($path, $disk = null)` | Remove it, tolerating one already gone |
| `deleteAfterCommit($path, $disk)` | Remove it only once the transaction commits |
| `exists`, `size` | Ask the disk |
| `path('products', $id, 'images')` | Build a logical path from config |
| `disk()` | The disk currently configured |

`store()` returns `App\Core\Support\StoredFile` — `disk`, `path`,
`originalName`, `mimeType`, `size` — whose `toArray()` merges straight into a
model.

### What callers never do

```php
Storage::disk('public')->put(...);          // names a disk
asset('storage/'.$product->image);          // builds a URL
'https://bucket.s3.amazonaws.com/'.$path;   // names a provider
```

### What they do instead

```php
$stored = $files->store($request->file('image'), $files->path('products', $product->id, 'images'));
$product->images()->create($stored->toArray());

$files->url($image->path, $image->disk);
$files->deleteAfterCommit($old->path, $old->disk);
```

## Decisions worth knowing

**Only the relative path is stored.** `products/25/images/abc123.png`, never a
URL. The URL is derived on read, which is what lets the provider change
without touching a single row.

**The row records its disk.** After `FILES_DISK` moves from `public` to `s3`,
files written earlier are still on the old disk. Because each row remembers
where its bytes went, they keep resolving — no backfill, no broken images. New
uploads go to the new disk. Copy the old files across and the column becomes
redundant; until then it is what makes the switch safe.

**The client filename never reaches the disk.** It may collide, contain a
path, or be crafted to look executable. Files are named with a UUID and the
original is kept in `original_name` for display only.

**A disk that cannot serve URLs returns `null`.** Laravel's local driver
invents `/storage/...` for a disk with no `url` key — a link that 404s.
`url()` checks the disk's configuration first and returns `null` instead, so a
page renders a placeholder rather than a broken image.

**Deletions wait for the commit.** Replacing an image writes a row and removes
bytes. Deleting eagerly loses the old file if the transaction then rolls back,
leaving a row pointing at nothing. `deleteAfterCommit()` means the worst case
is an orphaned file — recoverable, unlike the reverse.

## Product images

`product_images` — a table, because a product has many images, they have an
order, and one is primary.

| Column | Notes |
| --- | --- |
| `product_id`, `product_variant_id` | variant optional, for per-variant imagery |
| `disk`, `path` | where it is, relatively |
| `original_name`, `mime_type`, `size` | for display and auditing |
| `sort_order` | the gallery order |
| `is_primary` | which one represents the product |

`Modules\Inventory\Services\ProductImageService` owns the lifecycle: `add`,
`replace`, `makePrimary`, `reorder`, `delete`, `deleteAll`. The first image a
product gets becomes primary, and deleting the primary promotes the next, so a
product with images always has one to show.

**Ordering and primary are separate.** `sort_order` is the arrangement the user
made; promoting an image marks which represents the product without dragging it
to the front of the gallery. `Product::primaryImage()` prefers the flagged one
and falls back to the first in order.

Every serialised image carries a `url`, so no Vue component builds one:

```json
{ "id": 4, "path": "products/25/images/abc.png", "url": "http://…/storage/products/25/images/abc.png" }
```

Upload limits live in `config/files.php` and are shared with the frontend as
`$page.props.fileLimits`, so a form cannot disagree with the validation rule.

### Two ways in

A product being **created** has no id to upload against, so its files ride
along with the create request (`images[]`) and are stored by the same service
the moment the row exists. The create form uses
`ProductImageDropzone.vue` — files staged in the browser, previewed from
object URLs, nothing sent until the product is submitted, so an abandoned form
leaves no orphaned uploads.

An **existing** product uses `ProductImageManager.vue` on the edit screen,
which talks to the endpoints below and can reorder and promote what is already
stored. Both paths go through `ProductImageService::add()`, so ordering and the
primary flag behave identically however a product was created.

### Endpoints

| Action | Route |
| --- | --- |
| Upload | `POST inventory/products/{product}/images` |
| Reorder | `PATCH inventory/products/{product}/images/reorder` |
| Make primary | `PATCH inventory/products/{product}/images/{image}/primary` |
| Remove | `DELETE inventory/products/{product}/images/{image}` |

All guarded by `products.update`, and an image id belonging to another product
returns 404 rather than being trusted.

## One photo on a record

A gallery is a table; a single photo is two columns. `App\Core\Concerns\HasAvatar`
gives a model `avatar_disk` + `avatar_path` and an appended `avatar_url`, and
`App\Core\Services\AvatarService` does the writing:

```php
$avatars->sync($customer, $request->file('avatar'), $request->boolean('remove_avatar'));
```

`sync()` covers the three things a form can mean — a new photo, its removal, or
neither — so no controller repeats those branches. Replacing removes the bytes
it replaced, after the commit. Customers use it today; any model that adds the
trait and the two columns can use it without new upload code.

**Removal is its own intent.** A form that simply omits the file field is not
asking for the photo to be deleted, which is why `remove_avatar` exists rather
than treating an absent file as "clear it".

The create form's panel is `AvatarUploadField.vue` — the photo travels with the
form, so an abandoned form leaves nothing on disk.

## Local setup

The `public` disk serves through a symlink:

```bash
php artisan storage:link
```

Soft-deleting a product keeps its image rows and files — the product can be
restored. `ProductImageService::deleteAll()` is there for a permanent removal.
