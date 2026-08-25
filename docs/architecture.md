# Architecture

## Layout

```
app/
├── Core/                     shared building blocks (see AGENTS.md)
│   ├── BaseModel             UUID + soft deletes  (domain aggregates)
│   ├── BaseUuidModel         UUID, no soft deletes (pivots, line items, ledger)
│   ├── BaseApiController     JSON controllers (ApiResponse envelope)
│   ├── Concerns/HasVariantKey mirrors a nullable variant id into a non-null key
│   └── Support/{Permissions,Roles}  permission catalogue and default roles
├── Http/Controllers/Auth/    session authentication
└── Http/Middleware/          Inertia sharing, EnsureUserIsActive

modules/
├── Access/                   users, roles, permissions
└── Inventory/                the store domain
```

Two modules, not eleven. `modules/README.md` forbids importing across module
boundaries (cross-module talk goes through events), and the store entities are
one transactional bounded context: an order reserves stock, fulfilling it deducts
it, a receipt adds it, all inside single database transactions with foreign keys
between the tables. Splitting catalogue, stock, orders and customers into
separate modules would have meant either breaking that rule on every call or
routing transactional work through events, which cannot be rolled back
together.

`Access` is separate because it governs `App\Models\User` — a shared model in
`app/`, not another module's — and has no transactional coupling to stock.

## Inventory module

```
modules/Inventory/
├── Actions/          state transitions that move stock
├── Enums/            statuses, movement types, transition tables
├── Exceptions/       domain rule violations (rendered as 422 / field errors)
├── Http/
│   ├── Controllers/          Inertia pages
│   ├── Controllers/Api/      read-only JSON
│   └── Requests/             validation
├── Models/
├── Policies/
├── Services/         CRUD, queries, and the single stock write path
├── Support/          StockableUnit, MovementContext, DocumentNumberGenerator
├── Database/{Migrations,Factories}
├── Resources/js/pages/       module-owned Inertia pages
└── Routes/{web,api}.php
```

### Request flow

```
Route (auth + permission middleware)
  → FormRequest (validation)
    → Controller (policy check, no business logic)
      → Service (CRUD/queries)  or  Action (state transition + stock effect)
        → InventoryService (the only writer of stock)
          → inventory_items (current stock) + stock_movements (audit ledger)
```

Controllers never contain business rules. Anything that changes stock goes
through an Action, and every Action delegates the actual quantity change to
`InventoryService`.

### Services vs Actions

- **Services** own listing, filtering, pagination and plain CRUD:
  `CategoryService`, `SupplierService`, `ProductService`,
  `InboundReceiptService`, `OrderService`, `CustomerService`,
  `StockQueryService`, `StockPlannerService`, `DashboardService`,
  `ExpenseService`, `ReportService`.
- **Actions** own the transitions that have an inventory effect:
  `ReceiveInboundReceiptAction`, `CancelInboundReceiptAction`,
  `AdjustStockAction`, `ConfirmOrderAction`, `CancelOrderAction`,
  `FulfillOrderAction`.

### Models and relationships

| Model | Key relationships |
| --- | --- |
| `Category` | self-referencing `parent`/`children`, `hasMany` products |
| `Supplier` | `belongsToMany` products (via `ProductSupplier`), `hasMany` primaryProducts, inboundReceipts, stockMovements |
| `Product` | `belongsTo` category and primarySupplier, `hasMany` variants + inventoryItems + stockMovements, `belongsToMany` suppliers |
| `ProductVariant` | `belongsTo` product, `hasOne` inventoryItem |
| `ProductSupplier` | pivot with commercial terms (supplier SKU, unit cost, MOQ, lead time, preferred) |
| `InventoryItem` | current stock for one product (+ optional variant) |
| `StockMovement` | append-only ledger row; polymorphic-style `reference_type`/`reference_id` |
| `InboundReceipt` / `InboundReceiptItem` | receiving document and its lines |
| `Order` / `OrderItem` | sales order and its lines; `belongsTo` customer (nullable — a walk-in sale has none) |
| `Customer` | who the store sells to; orders keep their own contact snapshot |
| `Expense` | operating cost recorded against a trading day; never a stock purchase |

### Enums as the transition table

`OrderStatus` and `InboundReceiptStatus` each expose
`allowedTransitions()` and `canTransitionTo()`. Actions ask the enum instead of
hard-coding status strings, so the rules live in one place and the UI can render
the same list (controllers pass `allowedTransitions` to the page).

## Frontend

Module-owned Vue pages live in `modules/<Module>/Resources/js/pages` and are
addressed as `Inventory::Products/Index`. `resources/js/app.ts` resolves the
`Module::Page/Name` form against a glob over `modules/*/Resources/js/pages`;
anything without `::` resolves from `resources/js/pages` as before.
