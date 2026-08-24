# Suppliers

## Structure

`suppliers` (UUID key, soft deletes) carries identity, contact, address, tax and
commercial fields: `code` (unique), `company_name`, `contact_name`, `email`,
`phone`, `phone_alt`, `website`, `address_line1/2`, `city`, `state`,
`postal_code`, `country`, `tax_number`, `payment_terms`, `notes`, `status`.

Search covers company name, code, contact name, email and phone
(`Supplier::scopeSearch`); listings filter by status and country and are
paginated with query strings preserved.

## Product relationships

Both shapes are supported, on purpose:

1. **`products.primary_supplier_id`** — the usual "who do we normally buy this
   from" pointer, nulled (not cascaded) if the supplier is removed.
2. **`product_supplier` pivot** — many suppliers per product, modelled as a real
   Eloquent model (`ProductSupplier`) rather than a bare pivot so its commercial
   terms can grow without restructuring the relationship:

   | Column | Purpose |
   | --- | --- |
   | `product_id`, `product_variant_id` | the product, optionally a specific variant |
   | `variant_key` | non-null mirror of `product_variant_id` so the unique index works |
   | `supplier_id` | the supplier |
   | `supplier_sku` | their part number |
   | `unit_cost` | their price |
   | `minimum_order_quantity` | MOQ |
   | `lead_time_days` | lead time |
   | `is_preferred` | preferred source flag |

   Unique on `(product_id, variant_key, supplier_id)`.

Starting with the pivot means introducing multi-sourcing later is a data change,
not a schema migration.

### Why `variant_key` exists

MySQL treats `NULL`s in a unique index as distinct, so a unique index over a
nullable `product_variant_id` would happily accept `('p1', NULL)` twice.
`App\Core\Concerns\HasVariantKey` mirrors the id into a non-null string (`''`
for "no variant") on every save, and the index covers that column instead. The
same trait backs `inventory_items`.

## Receiving from a supplier

`inbound_receipts` + `inbound_receipt_items`, driven by
`ReceiveInboundReceiptAction` — see [inventory.md](inventory.md) for the stock
mechanics. Validation in `StoreInboundReceiptRequest` enforces:

- a supplier is **required** when the source requires one
  (`InboundSource::requiresSupplier()` — `supplier`, `purchase`);
- the supplier must be **active** — stock cannot be received from an
  inactive supplier;
- every variant must belong to the product on its own line.

## History integrity

Supplier history stays valid because:

- `stock_movements.supplier_id` uses `nullOnDelete`, and movements are never
  deleted;
- `inbound_receipts.supplier_id` uses `restrictOnDelete`;
- `SupplierService::delete()` refuses to delete a supplier that has any receipt
  or movement, and tells the caller to deactivate instead;
- deletion, where allowed, is a **soft** delete;
- deactivating a supplier (`status = inactive`) changes nothing historical — it
  only removes them from the pickers and blocks new receiving.

`SupplierService::history()` returns what a supplier detail screen needs:
products supplied, total received quantity, last receiving date and recent
receipts. It is loaded through `Inertia::defer()` so the page renders first.
