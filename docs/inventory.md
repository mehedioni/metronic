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
| `issueForShipment($unit, $qty, $context)` | deducts on-hand **and** releases the reservation in one transaction |
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

Outbound: `adjustment_decrease`, `shipment_out`, `damage`, `manual_removal`,
`transfer_out`, `receipt_reversal`.

`StockMovementType::manualValues()` is the subset a user may pick in the adjust
form — `shipment_out` and `receipt_reversal` are system-driven only.

## What creates a movement

| Event | Movement | Idempotency guard |
| --- | --- | --- |
| Receipt transitions to `received` (`ReceiveInboundReceiptAction`) | one inbound movement per receipt line, type from `InboundSource::movementType()` | `inbound_receipts.processed_at`, checked under a row lock in the same transaction |
| Processed receipt cancelled (`CancelInboundReceiptAction`) | `receipt_reversal` per line | `cancelled_at` + only reverses when `processed_at` was set |
| Manual adjustment (`AdjustStockAction`) | the chosen manual type | none needed — each call is an explicit user action |
| Shipment dispatched (`DispatchShipmentAction`) | `shipment_out` per line, plus reservation release | `shipments.shipped_at`, checked under a row lock |
| Dispatched shipment cancelled (`TransitionShipmentAction`) | `customer_return` per line, re-reserved if the order still holds a reservation | `shipped_at` is cleared in the same transaction |
| Order cancelled after shipping (`CancelOrderAction`) | `customer_return` for the shipped quantity | `order_items.quantity_shipped` reset to 0 in the same transaction |

## Order lifecycle and its inventory effects

```
draft ──► pending ──► confirmed ──► processing ──► shipped ──► completed
   │          │            │             │            │
   └──────────┴────────────┴─────────────┴────────────┴──► cancelled
```

- `pending → confirmed` (`ConfirmOrderAction`): **reserves** stock for every
  line. Rejected with `InsufficientStockException` if a line exceeds available
  stock, unless `config('inventory.allow_overselling')` is true. A second
  confirm is rejected by the transition table, so a reservation is taken once.
- Shipment dispatched: **deducts** on-hand, releases the matching reservation,
  increments `order_items.quantity_shipped`, and advances the order to
  `processing` (partially shipped) or `shipped` (fully shipped).
- `→ cancelled` (`CancelOrderAction`): releases the outstanding reservation and
  returns any already-shipped units to stock as `customer_return`.

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
