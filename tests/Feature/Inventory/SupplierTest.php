<?php

use App\Core\Support\Permissions;
use Modules\Inventory\Models\InboundReceipt;
use Modules\Inventory\Models\Supplier;

beforeEach(function () {
    $this->manager = userWithPermissions([
        Permissions::SUPPLIERS_VIEW,
        Permissions::SUPPLIERS_CREATE,
        Permissions::SUPPLIERS_UPDATE,
        Permissions::SUPPLIERS_DELETE,
    ]);
});

it('creates a supplier', function () {
    $this->actingAs($this->manager)->post('/inventory/suppliers', [
        'code' => 'ACME-1',
        'company_name' => 'Acme Supplies',
        'contact_name' => 'Jane Doe',
        'email' => 'jane@acme.test',
        'country' => 'US',
    ])->assertSessionHasNoErrors();

    expect(Supplier::query()->where('code', 'ACME-1')->exists())->toBeTrue();
});

it('rejects a duplicate supplier code', function () {
    Supplier::factory()->create(['code' => 'DUP-1']);

    $this->actingAs($this->manager)
        ->post('/inventory/suppliers', ['code' => 'DUP-1', 'company_name' => 'Other'])
        ->assertSessionHasErrors('code');
});

it('validates supplier input', function () {
    $this->actingAs($this->manager)
        ->post('/inventory/suppliers', ['code' => '', 'company_name' => '', 'email' => 'nope'])
        ->assertSessionHasErrors(['code', 'company_name', 'email']);
});

it('filters suppliers by status', function () {
    Supplier::factory()->create(['company_name' => 'Active Co']);
    Supplier::factory()->inactive()->create(['company_name' => 'Dormant Co']);

    $this->actingAs($this->manager)
        ->get('/inventory/suppliers?status=inactive')
        ->assertOk();
});

it('toggles supplier status without touching history', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->manager)
        ->patch("/inventory/suppliers/{$supplier->id}/status")
        ->assertSessionHasNoErrors();

    expect($supplier->refresh()->status->value)->toBe('inactive');
});

it('refuses to delete a supplier that has receiving history', function () {
    $supplier = Supplier::factory()->create();
    InboundReceipt::factory()->create(['supplier_id' => $supplier->id]);

    $this->actingAs($this->manager)
        ->from('/inventory/suppliers')
        ->delete("/inventory/suppliers/{$supplier->id}")
        ->assertSessionHasErrors();

    expect(Supplier::query()->whereKey($supplier->id)->exists())->toBeTrue();
});

it('deletes a supplier with no history', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->manager)
        ->delete("/inventory/suppliers/{$supplier->id}")
        ->assertSessionHasNoErrors();

    expect(Supplier::query()->whereKey($supplier->id)->exists())->toBeFalse();
    expect(Supplier::withTrashed()->whereKey($supplier->id)->exists())->toBeTrue();
});
