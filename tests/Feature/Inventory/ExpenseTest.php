<?php

use App\Core\Support\Permissions;
use Illuminate\Support\Carbon;
use Modules\Inventory\Enums\ExpenseCategory;
use Modules\Inventory\Models\Expense;
use Modules\Inventory\Services\ExpenseService;

beforeEach(function () {
    $this->bookkeeper = userWithPermissions([
        Permissions::EXPENSES_VIEW,
        Permissions::EXPENSES_CREATE,
        Permissions::EXPENSES_UPDATE,
        Permissions::EXPENSES_DELETE,
    ]);
});

it('records an expense against a trading day', function () {
    $this->actingAs($this->bookkeeper)->post('/inventory/expenses', [
        'spent_on' => Carbon::now()->toDateString(),
        'category' => ExpenseCategory::Rent->value,
        'amount' => 2400,
        'description' => 'Shop rent',
    ])->assertSessionHasNoErrors();

    $expense = Expense::query()->firstOrFail();

    expect($expense->category)->toBe(ExpenseCategory::Rent)
        ->and($expense->amount)->toBe('2400.00')
        // The recorder is captured, so the ledger is attributable.
        ->and($expense->created_by)->toBe($this->bookkeeper->id);
});

it('refuses an expense dated in the future', function () {
    $this->actingAs($this->bookkeeper)->post('/inventory/expenses', [
        'spent_on' => Carbon::now()->addDay()->toDateString(),
        'category' => ExpenseCategory::Rent->value,
        'amount' => 100,
    ])->assertSessionHasErrors('spent_on');
});

it('refuses an unknown category', function () {
    $this->actingAs($this->bookkeeper)->post('/inventory/expenses', [
        'spent_on' => Carbon::now()->toDateString(),
        'category' => 'stock-purchase',
        'amount' => 100,
    ])->assertSessionHasErrors('category');
});

it('refuses a zero or negative amount', function (float $amount) {
    $this->actingAs($this->bookkeeper)->post('/inventory/expenses', [
        'spent_on' => Carbon::now()->toDateString(),
        'category' => ExpenseCategory::Other->value,
        'amount' => $amount,
    ])->assertSessionHasErrors('amount');
})->with([0, -50]);

it('updates an expense', function () {
    $expense = Expense::factory()->create(['amount' => 100]);

    $this->actingAs($this->bookkeeper)
        ->put("/inventory/expenses/{$expense->id}", ['amount' => 175])
        ->assertSessionHasNoErrors();

    expect($expense->refresh()->amount)->toBe('175.00');
});

it('deletes an expense', function () {
    $expense = Expense::factory()->create();

    $this->actingAs($this->bookkeeper)
        ->delete("/inventory/expenses/{$expense->id}")
        ->assertSessionHasNoErrors();

    expect(Expense::query()->whereKey($expense->id)->exists())->toBeFalse();
});

it('filters the list by category and date range', function () {
    Expense::factory()->create([
        'spent_on' => Carbon::now()->toDateString(),
        'category' => ExpenseCategory::Rent,
    ]);
    Expense::factory()->create([
        'spent_on' => Carbon::now()->subDays(40)->toDateString(),
        'category' => ExpenseCategory::Marketing,
    ]);

    $this->actingAs($this->bookkeeper)
        ->get('/inventory/expenses?category=rent')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory::Expenses/Index')
            ->has('expenses.data', 1));

    $this->actingAs($this->bookkeeper)
        ->get('/inventory/expenses?from='.Carbon::now()->subDays(7)->toDateString())
        ->assertInertia(fn ($page) => $page->has('expenses.data', 1));
});

it('summarises expenses over the same filters as the list', function () {
    Expense::factory()->create(['category' => ExpenseCategory::Rent, 'amount' => 100]);
    Expense::factory()->create(['category' => ExpenseCategory::Rent, 'amount' => 50]);
    Expense::factory()->create(['category' => ExpenseCategory::Fees, 'amount' => 25]);

    $summary = app(ExpenseService::class)->summary([]);

    expect($summary['count'])->toBe(3)
        ->and($summary['total'])->toBe(175.0)
        ->and($summary['by_category'])->toBe(['rent' => 150.0, 'fees' => 25.0]);
});

it('denies recording an expense without expenses.create', function () {
    $this->actingAs(userWithPermissions([Permissions::EXPENSES_VIEW]))
        ->post('/inventory/expenses', [
            'spent_on' => Carbon::now()->toDateString(),
            'category' => ExpenseCategory::Other->value,
            'amount' => 10,
        ])
        ->assertForbidden();
});

it('denies the list without expenses.view', function () {
    $this->actingAs(userWithPermissions([]))
        ->get('/inventory/expenses')
        ->assertForbidden();
});
