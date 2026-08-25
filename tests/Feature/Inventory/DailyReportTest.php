<?php

use App\Core\Support\Permissions;
use Illuminate\Support\Carbon;
use Modules\Inventory\Enums\ExpenseCategory;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Models\Expense;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\ReportService;

beforeEach(function () {
    $this->analyst = userWithPermissions([Permissions::REPORTS_VIEW]);
    $this->report = app(ReportService::class);
});

/**
 * An order placed on a given day, with one line priced and costed explicitly.
 */
function soldOn(
    string $date,
    float $price,
    ?float $cost,
    int $quantity = 1,
    ?Customer $customer = null,
    string $status = 'pending',
): Order {
    $product = Product::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer?->id,
        'customer_name' => $customer?->name ?? 'Walk-in',
        'status' => $status,
        'subtotal' => $price * $quantity,
        'total' => $price * $quantity,
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => $price,
        'unit_cost' => $cost,
        'line_total' => $price * $quantity,
    ]);

    $order->forceFill(['created_at' => Carbon::parse($date)->setTime(10, 0)])->save();

    return $order;
}

it('reports sales, cost of goods and gross profit per day', function () {
    $today = Carbon::now()->toDateString();

    soldOn($today, price: 100, cost: 60, quantity: 2);

    $report = $this->report->daily(['from' => $today, 'to' => $today]);
    $day = $report['days'][0];

    expect($day['sales'])->toBe(200.0)
        ->and($day['cogs'])->toBe(120.0)
        ->and($day['gross_profit'])->toBe(80.0)
        ->and($day['orders_count'])->toBe(1)
        ->and($report['totals']['gross_margin_percent'])->toBe(40.0);
});

it('subtracts recorded expenses to reach net profit', function () {
    $today = Carbon::now()->toDateString();

    soldOn($today, price: 100, cost: 40);
    Expense::factory()->create([
        'spent_on' => $today,
        'category' => ExpenseCategory::Rent,
        'amount' => 25,
    ]);

    $report = $this->report->daily(['from' => $today, 'to' => $today]);

    expect($report['days'][0]['expenses'])->toBe(25.0)
        ->and($report['days'][0]['net_profit'])->toBe(35.0)
        ->and($report['totals']['net_profit'])->toBe(35.0);
});

it('never counts a cancelled order', function () {
    $today = Carbon::now()->toDateString();

    soldOn($today, price: 100, cost: 40);
    soldOn($today, price: 999, cost: 500, status: 'cancelled');

    $report = $this->report->daily(['from' => $today, 'to' => $today]);

    expect($report['totals']['sales'])->toBe(100.0)
        ->and($report['totals']['orders_count'])->toBe(1);
});

it('reports a day with no trading as a zero row rather than omitting it', function () {
    $from = Carbon::now()->subDays(2)->toDateString();
    $to = Carbon::now()->toDateString();

    soldOn($to, price: 50, cost: 20);

    $report = $this->report->daily(['from' => $from, 'to' => $to]);

    expect($report['days'])->toHaveCount(3)
        ->and($report['days'][0]['sales'])->toBe(0.0)
        ->and($report['days'][0]['orders_count'])->toBe(0);
});

it('flags order lines that carry no cost, so profit is not silently overstated', function () {
    $today = Carbon::now()->toDateString();

    soldOn($today, price: 100, cost: null);

    $report = $this->report->daily(['from' => $today, 'to' => $today]);

    expect($report['days'][0]['lines_without_cost'])->toBe(1)
        ->and($report['meta']['lines_without_cost'])->toBe(1)
        // The unknown cost contributes nothing, which is why it must be flagged.
        ->and($report['days'][0]['cogs'])->toBe(0.0)
        ->and($report['days'][0]['gross_profit'])->toBe(100.0);
});

it('filters by customer name across the order snapshot and the linked record', function () {
    $today = Carbon::now()->toDateString();
    $emma = Customer::factory()->create(['name' => 'Emma Chen']);

    soldOn($today, price: 100, cost: 50, customer: $emma);
    soldOn($today, price: 400, cost: 100);

    $report = $this->report->daily([
        'from' => $today,
        'to' => $today,
        'customer' => 'Emma',
    ]);

    expect($report['totals']['sales'])->toBe(100.0)
        ->and($report['totals']['orders_count'])->toBe(1);
});

it('finds a walk-in sale by the name typed on the order', function () {
    $today = Carbon::now()->toDateString();

    $order = soldOn($today, price: 70, cost: 30);
    $order->forceFill(['customer_name' => 'Jeroen de Jong'])->save();

    $report = $this->report->daily([
        'from' => $today,
        'to' => $today,
        'customer' => 'Jeroen',
    ]);

    expect($report['totals']['sales'])->toBe(70.0);
});

it('withholds expenses while filtering by customer, because they are not attributable', function () {
    $today = Carbon::now()->toDateString();
    $emma = Customer::factory()->create(['name' => 'Emma Chen']);

    soldOn($today, price: 100, cost: 50, customer: $emma);
    Expense::factory()->create(['spent_on' => $today, 'amount' => 40]);

    $report = $this->report->daily([
        'from' => $today,
        'to' => $today,
        'customer' => 'Emma',
    ]);

    expect($report['meta']['expenses_attributable'])->toBeFalse()
        ->and($report['days'][0]['expenses'])->toBeNull()
        ->and($report['days'][0]['net_profit'])->toBeNull()
        ->and($report['totals']['net_profit'])->toBeNull()
        // Gross profit is still attributable, so it is still reported.
        ->and($report['totals']['gross_profit'])->toBe(50.0);
});

it('filters by a specific customer id', function () {
    $today = Carbon::now()->toDateString();
    $one = Customer::factory()->create();
    $two = Customer::factory()->create();

    soldOn($today, price: 10, cost: 5, customer: $one);
    soldOn($today, price: 900, cost: 5, customer: $two);

    $report = $this->report->daily([
        'from' => $today,
        'to' => $today,
        'customer_id' => $one->id,
    ]);

    expect($report['totals']['sales'])->toBe(10.0);
});

it('honours the date range and excludes what falls outside it', function () {
    $inside = Carbon::now()->subDays(2)->toDateString();
    $outside = Carbon::now()->subDays(20)->toDateString();

    soldOn($inside, price: 100, cost: 10);
    soldOn($outside, price: 500, cost: 10);

    $report = $this->report->daily([
        'from' => Carbon::now()->subDays(3)->toDateString(),
        'to' => Carbon::now()->toDateString(),
    ]);

    expect($report['totals']['sales'])->toBe(100.0);
});

it('swaps a backwards range instead of reporting nothing', function () {
    $today = Carbon::now()->toDateString();
    $earlier = Carbon::now()->subDays(3)->toDateString();

    soldOn($today, price: 100, cost: 10);

    $report = $this->report->daily(['from' => $today, 'to' => $earlier]);

    expect($report['range'])->toBe(['from' => $earlier, 'to' => $today])
        ->and($report['totals']['sales'])->toBe(100.0);
});

it('clamps an absurd range rather than building a decade of rows', function () {
    $report = $this->report->daily([
        'from' => Carbon::now()->subYears(8)->toDateString(),
        'to' => Carbon::now()->toDateString(),
    ]);

    expect(count($report['days']))->toBe(366);
});

it('reports margins as null when nothing sold', function () {
    $today = Carbon::now()->toDateString();

    $report = $this->report->daily(['from' => $today, 'to' => $today]);

    expect($report['totals']['sales'])->toBe(0.0)
        ->and($report['totals']['gross_margin_percent'])->toBeNull()
        ->and($report['totals']['net_margin_percent'])->toBeNull();
});

it('reports a loss when expenses exceed gross profit', function () {
    $today = Carbon::now()->toDateString();

    soldOn($today, price: 100, cost: 90);
    Expense::factory()->create(['spent_on' => $today, 'amount' => 50]);

    $report = $this->report->daily(['from' => $today, 'to' => $today]);

    expect($report['totals']['gross_profit'])->toBe(10.0)
        ->and($report['totals']['net_profit'])->toBe(-40.0);
});

it('breaks expenses down by category', function () {
    $today = Carbon::now()->toDateString();

    Expense::factory()->create([
        'spent_on' => $today,
        'category' => ExpenseCategory::Rent,
        'amount' => 100,
    ]);
    Expense::factory()->create([
        'spent_on' => $today,
        'category' => ExpenseCategory::Rent,
        'amount' => 50,
    ]);
    Expense::factory()->create([
        'spent_on' => $today,
        'category' => ExpenseCategory::Marketing,
        'amount' => 20,
    ]);

    $breakdown = $this->report->expensesByCategory([
        'from' => $today,
        'to' => $today,
    ]);

    expect($breakdown)->toBe(['rent' => 150.0, 'marketing' => 20.0]);
});

it('names every currency in range, so mixed sums can be called out', function () {
    $today = Carbon::now()->toDateString();

    soldOn($today, price: 100, cost: 10)->forceFill(['currency' => 'USD'])->save();
    soldOn($today, price: 100, cost: 10)->forceFill(['currency' => 'EUR'])->save();

    $report = $this->report->daily(['from' => $today, 'to' => $today]);

    expect($report['meta']['currencies'])->toHaveCount(2);
});

it('serves the report screen', function () {
    $this->actingAs($this->analyst)
        ->get('/inventory/reports/daily')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory::Reports/Daily')
            ->has('report.days')
            ->has('report.totals'));
});

it('denies the report without reports.view', function () {
    $this->actingAs(userWithPermissions([]))
        ->get('/inventory/reports/daily')
        ->assertForbidden();
});
