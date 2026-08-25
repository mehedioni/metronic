# Database

## Conventions

- **Auto-incrementing bigint primary keys** on every table, domain and
  framework alike, so every foreign key in the schema is the same 8-byte shape.
  InnoDB carries the primary key inside every secondary index, so a narrow key
  keeps those indexes small.
- **`products.uuid`** is the one public identifier: a unique random (v4) uuid
  beside the integer key. The key is what joins and foreign keys use; the uuid
  is what goes outward — catalogue links, exports, integrations — where a
  sequential number would leak how many products the store has and invite
  walking the range. It is generated in the model constructor, not from a
  "creating" event, because seeders mute model events and a NOT NULL unique
  column cannot depend on one. It is absent from the fillable list, so a
  request cannot set it.
- **Soft deletes** on aggregates (categories, suppliers, products, variants,
  receipts, orders, customers, expenses, users). Line items and pivots have none — their
  lifetime belongs to their parent. `stock_movements` has none either: it is an
  append-only ledger.
- **Statuses are string columns** cast to PHP enums, not database `ENUM`s, so
  adding a status is a code change rather than a schema migration.
- **Money** is `decimal(12,2)` and cast `decimal:2`.
- Indexes are declared in the create migration, on every column used for
  filtering, searching or joining.

## Tables

| Table | Notes |
| --- | --- |
| `users` | + `is_active` (indexed), `last_login_at`, `deleted_at` |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Spatie, published unchanged |
| `categories` | self FK `parent_id` (nullOnDelete), unique `slug` |
| `suppliers` | unique `code`; indexes on company name, email, phone, status |
| `products` | FKs to category + primary supplier (both nullOnDelete), unique `slug`, unique nullable `sku` |
| `product_variants` | FK to product (cascade), unique `sku`, JSON `options` |
| `product_supplier` | pivot with terms; unique `(product_id, variant_key, supplier_id)` |
| `inventory_items` | current stock; unique `(product_id, variant_key)`; index on `quantity_on_hand` |
| `stock_movements` | ledger; product/variant `restrictOnDelete`, supplier/user `nullOnDelete`; `timestamps(6)`; indexes on type, `(product_id, created_at)`, `(reference_type, reference_id)` |
| `inbound_receipts` / `inbound_receipt_items` | unique `reference_number`; supplier `restrictOnDelete`; items cascade from the receipt, products restricted |
| `orders` / `order_items` | unique `order_number`; items cascade from the order, products restricted |
| `customers` | unique `code`; unique `email`; indexed `name`, `phone`, `country`, `status` |
| `expenses` | indexed `spent_on`, `category`, composite `(spent_on, category)`; supplier `nullOnDelete` |
| `products` | unique `uuid`, `slug`, `sku`; indexed `name`, `type`, `status` |

`stock_movements` uses microsecond timestamps because two movements for the same
unit inside one second must still order deterministically in an audit trail.

## Referential rules

| Direction | Rule | Why |
| --- | --- | --- |
| product/variant → stock_movements | `restrictOnDelete` | history must keep resolving |
| supplier → stock_movements | `nullOnDelete` | movement stays, attribution may not |
| supplier → inbound_receipts | `restrictOnDelete` | receiving history is a record |
| customer → orders | `nullOnDelete` on `orders.customer_id` | a deleted customer must not take their orders with them; the order keeps its own name/email snapshot |
| product → variants, receipt → items, order → items | `cascadeOnDelete` | lines belong to their parent |
| category → products, supplier → products | `nullOnDelete` | classification is optional |

Application-level guards refuse the destructive cases before the database has
to (`RestrictedDeletionException`): categories with products or children,
suppliers with receiving history, products with stock history, receipts that
have been processed and orders past `pending`.

## Concurrency

- All stock writes are wrapped in `DB::transaction()`.
- `lockForUpdate()` is taken on the `inventory_items` row before its quantity is
  read.
- Documents that move stock are locked and re-checked (`processed_at`,
  `shipped_at`) inside the transaction, so a duplicate request is rejected
  rather than double-counted.
- Composite unique indexes are the final arbiter for concurrent row creation.
