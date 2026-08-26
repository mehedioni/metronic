<?php

use App\Core\Support\Permissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductImage;
use Modules\Inventory\Services\ProductImageService;

beforeEach(function () {
    Storage::fake('public');
    config()->set('files.disk', 'public');

    $this->manager = userWithPermissions([
        Permissions::PRODUCTS_VIEW,
        Permissions::PRODUCTS_UPDATE,
    ]);

    $this->product = Product::factory()->create();
});

it('uploads images under the product they belong to', function () {
    $this->actingAs($this->manager)
        ->post("/inventory/products/{$this->product->id}/images", [
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
        ])
        ->assertSessionHasNoErrors();

    $images = $this->product->images()->get();

    expect($images)->toHaveCount(2);

    foreach ($images as $image) {
        expect($image->path)->toStartWith("products/{$this->product->id}/images/")
            // The database holds a path, never a URL.
            ->and($image->path)->not->toContain('http')
            ->and($image->disk)->toBe('public');

        Storage::disk('public')->assertExists($image->path);
    }
});

it('makes the first image primary, and only the first', function () {
    app(ProductImageService::class)->add($this->product, [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
    ]);

    $images = $this->product->images()->get();

    expect($images->where('is_primary', true))->toHaveCount(1)
        ->and($images->first()->is_primary)->toBeTrue()
        ->and($images->last()->is_primary)->toBeFalse();
});

it('appends later uploads after the images already there', function () {
    $service = app(ProductImageService::class);

    $service->add($this->product, [UploadedFile::fake()->image('a.jpg')]);
    $service->add($this->product, [UploadedFile::fake()->image('b.jpg')]);

    expect($this->product->images()->pluck('sort_order')->all())->toBe([0, 1]);
});

it('serialises a URL for the frontend, so no component builds one', function () {
    $image = app(ProductImageService::class)
        ->add($this->product, [UploadedFile::fake()->image('a.jpg')])[0];

    $payload = $image->toArray();

    expect($payload)->toHaveKey('url')
        ->and($payload['url'])->toContain($image->path)
        ->and($payload['path'])->not->toContain('http');
});

it('promotes one image and demotes the previous primary', function () {
    $service = app(ProductImageService::class);
    [$first, $second] = $service->add($this->product, [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
    ]);

    $this->actingAs($this->manager)
        ->patch("/inventory/products/{$this->product->id}/images/{$second->id}/primary")
        ->assertSessionHasNoErrors();

    expect($second->refresh()->is_primary)->toBeTrue()
        ->and($first->refresh()->is_primary)->toBeFalse();
});

it('reorders images and ignores ids from another product', function () {
    $service = app(ProductImageService::class);
    [$a, $b, $c] = $service->add($this->product, [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
        UploadedFile::fake()->image('c.jpg'),
    ]);

    $other = Product::factory()->create();
    $foreign = $service->add($other, [UploadedFile::fake()->image('x.jpg')])[0];

    $this->actingAs($this->manager)
        ->patch("/inventory/products/{$this->product->id}/images/reorder", [
            'images' => [$c->id, $foreign->id, $a->id, $b->id],
        ])
        ->assertSessionHasNoErrors();

    expect($this->product->images()->pluck('id')->all())
        ->toBe([$c->id, $a->id, $b->id])
        // The other product's image was untouched.
        ->and($foreign->refresh()->sort_order)->toBe(0);
});

it('deletes the row and the file', function () {
    $image = app(ProductImageService::class)
        ->add($this->product, [UploadedFile::fake()->image('a.jpg')])[0];
    $path = $image->path;

    $this->actingAs($this->manager)
        ->delete("/inventory/products/{$this->product->id}/images/{$image->id}")
        ->assertSessionHasNoErrors();

    expect(ProductImage::query()->whereKey($image->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});

it('promotes the next image when the primary is deleted', function () {
    $service = app(ProductImageService::class);
    [$first, $second] = $service->add($this->product, [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
    ]);

    $service->delete($first);

    // A product with images always has one to show.
    expect($second->refresh()->is_primary)->toBeTrue();
});

it('refuses an image belonging to another product', function () {
    $other = Product::factory()->create();
    $foreign = app(ProductImageService::class)
        ->add($other, [UploadedFile::fake()->image('x.jpg')])[0];

    $this->actingAs($this->manager)
        ->delete("/inventory/products/{$this->product->id}/images/{$foreign->id}")
        ->assertNotFound();

    expect(ProductImage::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('replaces an image, keeping its place and removing the old file', function () {
    $service = app(ProductImageService::class);
    [$first, $second] = $service->add($this->product, [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
    ]);

    $oldPath = $second->path;
    $replaced = $service->replace($second, UploadedFile::fake()->image('new.jpg'));

    expect($replaced->path)->not->toBe($oldPath)
        ->and($replaced->sort_order)->toBe(1)
        ->and($replaced->original_name)->toBe('new.jpg');

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($replaced->path);
});

it('rejects a file that is not an image', function () {
    $this->actingAs($this->manager)
        ->post("/inventory/products/{$this->product->id}/images", [
            'images' => [UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')],
        ])
        ->assertSessionHasErrors('images.0');

    expect($this->product->images()->count())->toBe(0);
});

it('rejects an image over the configured size', function () {
    config()->set('files.images.max_kilobytes', 10);

    $this->actingAs($this->manager)
        ->post("/inventory/products/{$this->product->id}/images", [
            'images' => [UploadedFile::fake()->image('huge.jpg')->size(50)],
        ])
        ->assertSessionHasErrors('images.0');
});

it('denies uploading without products.update', function () {
    $viewer = userWithPermissions([Permissions::PRODUCTS_VIEW]);

    $this->actingAs($viewer)
        ->post("/inventory/products/{$this->product->id}/images", [
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ])
        ->assertForbidden();
});

it('sends the primary image with each row of the product list', function () {
    app(ProductImageService::class)->add($this->product, [UploadedFile::fake()->image('a.jpg')]);

    $this->actingAs($this->manager)
        ->get('/inventory/products')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data.0.primary_image')
            ->where('products.data.0.primary_image.url', fn ($url) => str_contains((string) $url, 'products/')));
});

it('stores images that came with the create form', function () {
    $creator = userWithPermissions([
        Permissions::PRODUCTS_VIEW,
        Permissions::PRODUCTS_CREATE,
    ]);

    $this->actingAs($creator)
        ->post('/inventory/products', [
            'name' => 'Runner',
            'sku' => 'RUN-1',
            'images' => [
                UploadedFile::fake()->image('front.jpg'),
                UploadedFile::fake()->image('back.png'),
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $product = Product::where('sku', 'RUN-1')->sole();
    $images = $product->images()->ordered()->get();

    expect($images)->toHaveCount(2)
        // The first one chosen represents the product in lists.
        ->and($images[0]->is_primary)->toBeTrue()
        ->and($images[1]->is_primary)->toBeFalse()
        ->and($images->pluck('original_name')->all())->toBe(['front.jpg', 'back.png']);

    foreach ($images as $image) {
        // Filed under the product that now owns it, not a temporary location.
        expect($image->path)->toStartWith("products/{$product->id}/images/");
        Storage::disk('public')->assertExists($image->path);
    }
});

it('creates the product without images when none were chosen', function () {
    $creator = userWithPermissions([
        Permissions::PRODUCTS_VIEW,
        Permissions::PRODUCTS_CREATE,
    ]);

    $this->actingAs($creator)
        ->post('/inventory/products', ['name' => 'Plain', 'sku' => 'PLN-1'])
        ->assertSessionHasNoErrors();

    expect(Product::where('sku', 'PLN-1')->sole()->images)->toBeEmpty();
});

it('refuses a create upload that is not an image', function () {
    $creator = userWithPermissions([
        Permissions::PRODUCTS_VIEW,
        Permissions::PRODUCTS_CREATE,
    ]);

    $this->actingAs($creator)
        ->post('/inventory/products', [
            'name' => 'Bad upload',
            'sku' => 'BAD-1',
            'images' => [UploadedFile::fake()->create('invoice.pdf', 40, 'application/pdf')],
        ])
        ->assertSessionHasErrors('images.0');

    // The whole submission fails, so no half-made product is left behind.
    expect(Product::where('sku', 'BAD-1')->exists())->toBeFalse()
        ->and(ProductImage::count())->toBe(0);
});
