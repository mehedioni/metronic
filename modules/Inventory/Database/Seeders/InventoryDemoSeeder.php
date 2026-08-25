<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Actions\CancelOrderAction;
use Modules\Inventory\Actions\ConfirmOrderAction;
use Modules\Inventory\Actions\FulfillOrderAction;
use Modules\Inventory\Actions\ReceiveInboundReceiptAction;
use Modules\Inventory\Enums\ExpenseCategory;
use Modules\Inventory\Enums\InboundReceiptStatus;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Models\Expense;
use Modules\Inventory\Models\InboundReceipt;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\MovementContext;
use Modules\Inventory\Support\StockableUnit;

/**
 * Local demo data, so every screen has something realistic to render.
 *
 * Stock is built the same way the application builds it — through
 * InventoryService and the order actions — so the ledger, the reservations
 * and the planner figures all agree with each other. Never run outside local.
 */
class InventoryDemoSeeder extends Seeder
{
    private const CATEGORIES = [
        'Sneakers', 'Boots', 'Sandals', 'Accessories', 'Apparel',
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('InventoryDemoSeeder only runs in the local environment.');

            return;
        }

        $categories = $this->categories();
        $suppliers = Supplier::factory()->count(6)->create();
        $customers = Customer::factory()->count(24)->create();
        $products = $this->products($categories, $suppliers);

        $this->openingStock($products);
        $this->receivingHistory($products, $suppliers);
        $this->salesHistory($products, $customers);
        $this->operatingExpenses();
        $this->alignLedgerDates();

        $this->command?->info(sprintf(
            'Seeded %d categories, %d suppliers, %d customers, %d products.',
            $categories->count(),
            $suppliers->count(),
            $customers->count(),
            $products->count(),
        ));
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        return collect(self::CATEGORIES)->map(fn (string $name): Category => Category::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => "Everything in {$name}.",
        ]));
    }

    /**
     * A mix of simple and variable products, each with supplier terms so the
     * planner has a real lead time to work with.
     *
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, Supplier>  $suppliers
     * @return Collection<int, Product>
     */
    private function products($categories, $suppliers): Collection
    {
        $products = collect();

        foreach ($categories as $category) {
            $simple = Product::factory()->count(4)->create([
                'category_id' => $category->id,
                'primary_supplier_id' => $suppliers->random()->id,
            ]);

            $variable = Product::factory()->variable()->count(2)->create([
                'category_id' => $category->id,
                'primary_supplier_id' => $suppliers->random()->id,
            ]);

            foreach ($variable as $product) {
                ProductVariant::factory()->count(3)->create(['product_id' => $product->id]);
            }

            $products = $products->concat($simple)->concat($variable);
        }

        foreach ($products as $product) {
            $product->suppliers()->attach($suppliers->random()->id, [
                'id' => Str::uuid()->toString(),
                'variant_key' => '',
                'supplier_sku' => 'S-'.Str::upper(Str::random(6)),
                'unit_cost' => $product->cost_price,
                'minimum_order_quantity' => 10,
                'lead_time_days' => fake()->randomElement([3, 7, 14, 21]),
                'is_preferred' => true,
            ]);
        }

        return $products;
    }

    /**
     * Put stock on the shelf for every stockable unit, dated back across the
     * planner's velocity window so the numbers are not all from today.
     *
     * @param  Collection<int, Product>  $products
     */
    private function openingStock($products): void
    {
        $inventory = app(InventoryService::class);

        foreach ($products as $product) {
            foreach ($this->unitsFor($product) as $unit) {
                $movement = $inventory->record(
                    $unit,
                    StockMovementType::OpeningStock,
                    fake()->numberBetween(20, 160),
                    new MovementContext(
                        supplierId: $product->primary_supplier_id,
                        reason: 'Opening stock',
                    ),
                );

                $movement->forceFill([
                    'created_at' => Carbon::now()->subDays(fake()->numberBetween(30, 90)),
                ])->save();
            }
        }
    }

    /**
     * Receipts in each state the receiving screens show. The received ones go
     * through the real action, so they add stock and write the ledger.
     *
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Supplier>  $suppliers
     */
    private function receivingHistory($products, $suppliers): void
    {
        $receive = app(ReceiveInboundReceiptAction::class);

        foreach (range(1, 12) as $index) {
            $receivedOn = Carbon::now()->subDays(fake()->numberBetween(0, 60));

            $receipt = InboundReceipt::create([
                'reference_number' => 'GRN-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'supplier_id' => $suppliers->random()->id,
                'status' => InboundReceiptStatus::Pending,
                'received_date' => $receivedOn->toDateString(),
                'notes' => 'Restock delivery',
            ]);

            // alignLedgerDates() dates each movement from its document, so the
            // document itself has to be dated first.
            $receipt->forceFill([
                'created_at' => $receivedOn,
                'updated_at' => $receivedOn,
            ])->save();

            foreach ($products->random(fake()->numberBetween(1, 3)) as $product) {
                $variant = $product->variants()->inRandomOrder()->first();

                $receipt->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => fake()->numberBetween(10, 80),
                    'unit_cost' => $product->cost_price,
                ]);
            }

            // Two thirds already on the shelf, the rest still awaiting receipt.
            if ($index % 3 !== 0) {
                rescue(fn () => $receive->handle($receipt->refresh()), report: false);
            }
        }
    }

    /**
     * Orders in every state the list screen can show, driven through the real
     * actions so stock, reservations and the ledger stay consistent.
     *
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Customer>  $customers
     */
    private function salesHistory($products, $customers): void
    {
        $confirm = app(ConfirmOrderAction::class);
        $fulfill = app(FulfillOrderAction::class);
        $cancel = app(CancelOrderAction::class);

        foreach (range(1, 120) as $index) {
            $customer = $customers->random();
            $order = Order::factory()->create([
                'customer_id' => $customer->id,
                ...$customer->orderSnapshot(),
                'order_number' => 'ORD-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
            ]);

            $this->addLines($order, $products);
            $order->recalculateTotals();

            // Spread the orders across the last three months so the dashboard
            // charts and the customer aggregates have a shape to them.
            $order->forceFill([
                'created_at' => Carbon::now()->subDays(fake()->numberBetween(0, 90)),
            ])->save();

            match (true) {
                $index % 7 === 0 => $this->cancel($cancel, $order),
                $index % 5 === 0 => $this->confirmOnly($confirm, $order),
                $index % 3 === 0 => $this->partiallyFulfil($confirm, $fulfill, $order),
                default => $this->fulfil($confirm, $fulfill, $order),
            };
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function addLines(Order $order, $products): void
    {
        foreach ($products->random(fake()->numberBetween(1, 4)) as $product) {
            $variant = $product->variants()->inRandomOrder()->first();
            $price = (float) ($variant?->selling_price ?? $product->selling_price);
            // Cost is snapshotted the same way OrderService does it, or the
            // profit report would have revenue with no cost behind it.
            $cost = $variant?->cost_price ?? $product->cost_price;
            $quantity = fake()->numberBetween(1, 6);

            $order->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $quantity,
                'unit_price' => $price,
                'unit_cost' => $cost === null ? null : (float) $cost,
                'line_total' => round($price * $quantity, 2),
            ]);
        }
    }

    private function confirmOnly(ConfirmOrderAction $confirm, Order $order): void
    {
        rescue(fn () => $confirm->handle($order->refresh()), report: false);
    }

    private function fulfil(ConfirmOrderAction $confirm, FulfillOrderAction $fulfill, Order $order): void
    {
        rescue(function () use ($confirm, $fulfill, $order): void {
            $confirm->handle($order->refresh());
            $fulfill->handle($order->refresh());
        }, report: false);
    }

    private function partiallyFulfil(ConfirmOrderAction $confirm, FulfillOrderAction $fulfill, Order $order): void
    {
        rescue(function () use ($confirm, $fulfill, $order): void {
            $confirm->handle($order->refresh());
            $order->load('items');

            $lines = $order->items
                ->mapWithKeys(fn ($item): array => [
                    $item->getKey() => max(1, (int) floor($item->quantity / 2)),
                ])
                ->all();

            $fulfill->handle($order->refresh(), $lines);
        }, report: false);
    }

    private function cancel(CancelOrderAction $cancel, Order $order): void
    {
        rescue(fn () => $cancel->handle($order->refresh(), 'Customer changed their mind'), report: false);
    }

    /**
     * Running costs across the same period the sales cover, so the daily
     * profit report has both sides of the equation.
     *
     * Rent and salaries land once a month; the rest are sprinkled across the
     * days, which is roughly how a small shop's ledger looks.
     */
    private function operatingExpenses(): void
    {
        $today = Carbon::now()->startOfDay();

        foreach ([0, 1, 2] as $monthsAgo) {
            $firstOfMonth = $today->copy()->subMonths($monthsAgo)->startOfMonth();

            if ($firstOfMonth->greaterThan($today)) {
                continue;
            }

            Expense::factory()->create([
                'spent_on' => $firstOfMonth->toDateString(),
                'category' => ExpenseCategory::Rent,
                'amount' => 2400,
                'description' => 'Shop rent',
            ]);

            Expense::factory()->create([
                'spent_on' => $firstOfMonth->toDateString(),
                'category' => ExpenseCategory::Salaries,
                'amount' => 5200,
                'description' => 'Staff wages',
            ]);
        }

        $sprinkled = [
            ExpenseCategory::Utilities,
            ExpenseCategory::Marketing,
            ExpenseCategory::Logistics,
            ExpenseCategory::Maintenance,
            ExpenseCategory::Fees,
        ];

        foreach (range(1, 45) as $index) {
            Expense::factory()->create([
                'spent_on' => $today->copy()
                    ->subDays(fake()->numberBetween(0, 90))
                    ->toDateString(),
                'category' => fake()->randomElement($sprinkled),
                'amount' => fake()->randomFloat(2, 15, 420),
            ]);
        }
    }

    /**
     * Date each ledger row to the document that caused it.
     *
     * The actions run now, so every movement would otherwise be stamped
     * today and the dashboard's trend charts would show one spike. Only the
     * demo data is rewritten this way — the application never backdates a
     * movement.
     */
    private function alignLedgerDates(): void
    {
        foreach ([Order::class, InboundReceipt::class] as $model) {
            DB::table('stock_movements')
                ->join(
                    (new $model)->getTable().' as document',
                    'stock_movements.reference_id',
                    '=',
                    'document.id',
                )
                ->where('stock_movements.reference_type', $model)
                ->update([
                    'stock_movements.created_at' => DB::raw('document.created_at'),
                    'stock_movements.updated_at' => DB::raw('document.created_at'),
                ]);
        }
    }

    /**
     * Every stockable unit of a product: its variants, or the product itself
     * when it has none.
     *
     * @return array<int, StockableUnit>
     */
    private function unitsFor(Product $product): array
    {
        $variants = $product->variants()->pluck('id');

        if ($variants->isEmpty()) {
            return [new StockableUnit($product->id)];
        }

        return $variants
            ->map(fn (string $variantId): StockableUnit => new StockableUnit($product->id, $variantId))
            ->all();
    }
}
