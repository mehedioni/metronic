# Reports

`GET inventory/reports/daily` — daily sales, cost of goods sold, operating
expenses and profit, over a date range, optionally narrowed to one customer.

## The figures

| Figure | How it is arrived at |
| --- | --- |
| sales | `sum(orders.total)` for orders **placed** that day, cancelled ones excluded |
| cost of goods | `sum(order_items.unit_cost × quantity)` on those same orders |
| gross profit | sales − cost of goods |
| expenses | `sum(expenses.amount)` recorded against that trading day |
| net profit | gross profit − expenses |

Nothing is stored. `ReportService` aggregates orders, order lines and expenses
on read, so a report can never disagree with the records it describes.

Revenue and cost share one basis — the order's full quantity, on the day the
order was placed — so a line cannot contribute revenue in one period and its
cost in another.

## Stock purchases are not expenses

What inventory costs reaches the report as **cost of goods sold**, from the
order line that sold it. Recording the purchase as an expense as well would
subtract the same money twice, which is why `ExpenseCategory` has no
stock-purchase case and why receiving a delivery creates no expense row.

## Cost is snapshotted, not looked up

`order_items.unit_cost` is filled at intake from the variant's cost price,
falling back to the product's. It is never resolved at report time: the
product's cost price is *current*, and reading it later would silently restate
the margin on every past order whenever a supplier changes their prices.

A null cost stays null. An unknown cost must report as unknown rather than as
free, so the report counts those lines in `lines_without_cost` and the screen
says the margin shown is a best case. Fulfilment also copies the snapshot onto
the `order_out` ledger row, so the audit trail records what the stock leaving
was worth.

## Filters

| Filter | Effect |
| --- | --- |
| `from`, `to` | Inclusive date range. Defaults to the last 30 days, clamped to 366. A backwards range is swapped rather than rejected — that is a typo, not a request for an empty report. |
| `customer` | Free text, matched against the name and email snapshotted on the order **and** against the linked customer's name and code, so a walk-in sale typed as "Emma Chen" is found alongside that person's account. |
| `customer_id` | Exact customer. |

Every day in the range is present, including days with no trading: a gap has
to read as a zero, not as a missing row a chart would interpolate over.

### Expenses are withheld per customer

Filtering by customer returns `expenses` and `net_profit` as **null**, and
`meta.expenses_attributable` as false. Rent and wages belong to the store, not
to a buyer; dividing them across one customer's orders would produce a net
profit figure that means nothing. Sales, cost of goods and gross profit are
attributable, so those are still reported.

## Currency

Every figure is a plain sum, which is only meaningful while the range holds one
currency. `meta.currencies` lists what was found, and the screen warns rather
than quietly adding dollars to euros. Converting between currencies is out of
scope for a single-store system.

## Expenses

`inventory/expenses` records what it costs to run the store. Categories are a
fixed enum (`ExpenseCategory`) rather than free text, because a daily profit
report is only comparable when the same kind of spend lands in the same bucket
every time; the description carries the detail.

`spent_on` is a **date**, not a timestamp: an expense belongs to a trading day,
and the report groups by that day regardless of when the row was entered. It
cannot be dated in the future.

Nothing depends on an expense row, so unlike a supplier or a customer it is
deleted outright rather than deactivated.

Permissions: `reports.view`, and `expenses.view|create|update|delete`.
