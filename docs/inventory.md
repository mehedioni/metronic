# Inventory logic

This is the part of the system with the strictest rules. Read it before
changing anything that touches a quantity.

## How stock is stored

Two tables, with different jobs:

- **`inventory_items`** — the authoritative *current* stock for one stockable
  unit: `quantity_on_hand` and `quantity_reserved`. One row per product, or per
  product + variant. This is the only place a quantity is read from.
- **`stock_movements`** — an **append-only ledger** of every change to
  `quantity_on_hand`, recording `type`, signed `quantity`, `quantity_before`,
  `quantity_after`, the supplier where relevant, the referencing document
  (`reference_type` + `reference_id`), a reason, and the acting user. Rows are
  never updated or deleted, which is why the model has no soft deletes: a
  mistake is corrected by recording a **compensating movement**.

Available stock is `quantity_on_hand - quantity_reserved`
(`InventoryItem::availableQuantity()`).

Stock is never `products.stock = products.stock + 1`.

## Reservations are not movements

A reservation changes `quantity_reserved` only. On-hand does not move, so no
ledger row is written — writing one would claim stock changed when it did not.
The reservation's provenance is the order itself (`orders.confirmed_at` and the
order status).

## The single write path

`Modules\Inventory\Services\InventoryService` is the only class that writes a
quantity:

| Method | Effect |
| --- | --- |
| `record($unit, $type, $qty, $context)` | changes on-hand and appends one ledger row |
| `reserve($unit, $qty)` | increases `quantity_reserved` (rejects overselling) |
| `release($unit, $qty)` | decreases `quantity_reserved`, clamped at zero |
| `issueForOrder($unit, $qty, $context)` | deducts on-hand **and** releases the reservation in one transaction |
| `itemFor` / `onHandQuantity` / `availableQuantity` | reads |

Every write:

1. runs inside `DB::transaction()`;
2. takes `lockForUpdate()` on the `inventory_items` row **before** reading the
   quantity, so two concurrent requests for the same unit serialise instead of
   both acting on a stale number;
3. computes `before`/`after` and rejects the operation if `after < 0` and
   `config('inventory.allow_negative_stock')` is false;
4. writes the item row and the ledger row together.

`$quantity` is always a positive magnitude — direction comes from
`StockMovementType::direction()`.

Creating the first `inventory_items` row for a unit is race-tolerant: the
composite unique index is the arbiter and the loser of a race re-reads the
winner's row.

## Movement types

Inbound (`direction() === 1`): `opening_stock`, `stock_received`,
`customer_return`, `adjustment_increase`, `transfer_in`.

Outbound: `adjustment_decrease`, `order_out`, `damage`, `manual_removal`,
`transfer_out`, `receipt_reversal`.

`StockMovementType::manualValues()` is the subset a user may pick in the adjust
form — `order_out` and `receipt_reversal` are system-driven only.

## What creates a movement

| Event | Movement | Idempotency guard |
| --- | --- | --- |
| Receipt transitions to `received` (`ReceiveInboundReceiptAction`) | one inbound movement per receipt line, type from `InboundSource::movementType()` | `inbound_receipts.processed_at`, checked under a row lock in the same transaction |
| Processed receipt cancelled (`CancelInboundReceiptAction`) | `receipt_reversal` per line | `cancelled_at` + only reverses when `processed_at` was set |
| Manual adjustment (`AdjustStockAction`) | the chosen manual type | none needed — each call is an explicit user action |
| Order fulfilled (`FulfillOrderAction`) | `order_out` per line, plus reservation release | the order and each line's `quantity_fulfilled` are read under a row lock and written in the same transaction as the movements; a repeat call with nothing outstanding raises `AlreadyProcessedException` |
| Order cancelled after part of it was fulfilled (`CancelOrderAction`) | `customer_return` for the fulfilled quantity | `order_items.quantity_fulfilled` reset to 0 in the same transaction |

## Order lifecycle and its inventory effects

The lifecycle is **configuration**, not an enum: see `config/orders.php`.
Orders store `status_id`, never a name, so relabelling a status does not
rewrite a single row. The shipped default is:

```
draft ──► pending ──► confirmed ──► processing ──► completed
(Quote)      │            │             │
   └─────────┴────────────┴─────────────┴──► cancelled
```

Each status declares what it *means*, because the inventory effects are bound
to those meanings rather than to its name:

| Flag | Effect |
| --- | --- |
| `editable` | lines and totals may still change |
| `holds_reservation` | stock is reserved while the order sits here |
| `fulfillable` | the order may be handed over from here, deducting on-hand stock |
| `cancellable` | the order may still be cancelled |
| `void` | the order was called off; revenue, margin and customer spend exclude it |
| `transitions` | statuses it may move to, by key |

`Modules\Inventory\Support\OrderStatuses` resolves the configuration —
`key()`, `find()`, `resolve()` (id, key or object), `assignable()`,
`billableIds()`. `$order->status` hands back the value object carrying those
flags, and serialises to `{id, key, label, variant}` for the frontend.

Adding a status is a config edit: it gains a tab, a count and a filter with no
code change. Ids are permanent — reusing one for a different meaning would
silently restate every historical order.

- Intake (`OrderService::create`): records the order and its lines and has
  **no** inventory effect. A line with no `unit_price` is priced from the
  variant, falling back to the product. Quantities beyond what is available are
  accepted deliberately — a shop can write down an order it intends to restock
  for, and nothing is promised until confirmation. Lines stay editable while
  the status is `draft` or `pending`.
- `pending → confirmed` (`ConfirmOrderAction`): **reserves** stock for every
  line. Rejected with `InsufficientStockException` if a line exceeds available
  stock, unless `config('inventory.allow_overselling')` is true. A second
  confirm is rejected by the transition table, so a reservation is taken once.
- Fulfilment (`FulfillOrderAction`): **deducts** on-hand, releases the matching
  reservation, increments `order_items.quantity_fulfilled`, and advances the
  order to `processing` (partly fulfilled) or `completed` (fully fulfilled).
  This is the only outbound stock path for an order — confirmation reserves,
  fulfilment is what removes. Callers may pass per-line quantities for a
  partial handover; passing none fulfils everything outstanding.
- `→ cancelled` (`CancelOrderAction`): releases the outstanding reservation and
  returns any already-fulfilled units to stock as `customer_return`.

Order lines are only editable while the status is `draft` or `pending`
(`OrderStatus::isEditable()`), i.e. before the order has any inventory impact.

## Receiving lifecycle

```
draft ──► pending ──► received
   └──────────┴──────────┴──► cancelled
```

Only the transition into `received` moves stock. A receipt that has moved stock
can no longer be edited or deleted (`InboundReceiptService::assertEditable()`),
because its ledger rows would start describing something that no longer exists.

## Configuration

`config/inventory.php`:

| Key | Default | Effect |
| --- | --- | --- |
| `allow_negative_stock` | `false` | reject any movement that would push on-hand below zero |
| `allow_overselling` | `false` | reject a confirmation that exceeds available stock |
| `number_prefixes` | `GRN` / `ORD` / `SHP` | document number prefixes |
| `dashboard.recent_limit`, `dashboard.low_stock_limit` | 10 | dashboard query limits |

## Errors

Domain violations extend `InventoryException` and are rendered by
`InventoryServiceProvider` as HTTP 422 JSON for API requests and as field errors
for Inertia requests — never as a 500:
`InsufficientStockException` (`quantity`), `InvalidStatusTransitionException`
(`status`), `AlreadyProcessedException`, `RestrictedDeletionException`,
`CircularCategoryException` (`parent_id`).

## Reorder planning

`StockPlannerService` answers "what should be reordered, and when" without
storing anything:

| Figure | Derived from |
| --- | --- |
| daily velocity | outbound `stock_movements` over a 30-day window (`StockPlannerService::VELOCITY_WINDOW_DAYS`) |
| target level | the variant's `low_stock_threshold`, falling back to the product's — the same precedence `InventoryItem::scopeLowStock()` uses |
| lead time | the shortest `product_supplier.lead_time_days` on the product, or 7 days when no link states one |
| days of cover | available ÷ daily velocity, `null` when the unit never moves |
| suggested reorder | `max(0, target + velocity × lead time − available)` |

Because nothing is persisted, a planner row is always a statement about the
current ledger rather than a snapshot that can go stale. `GET
inventory/stock/planner` also accepts `reorder_within=<days>` to keep only the
units needing a reorder inside that horizon.
