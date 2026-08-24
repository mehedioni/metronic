<?php

use App\Core\Support\Permissions;
use App\Core\Support\Roles;
use App\Models\User;
use Modules\Inventory\Models\Supplier;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('denies a page to a user without the permission', function () {
    $user = userWithPermissions([Permissions::DASHBOARD_VIEW]);

    $this->actingAs($user)->get('/inventory/suppliers')->assertForbidden();
});

it('allows a page to a user holding the permission', function () {
    $user = userWithPermissions([Permissions::SUPPLIERS_VIEW]);

    $this->actingAs($user)->get('/inventory/suppliers')->assertOk();
});

it('grants every ability to a super admin', function () {
    $this->actingAs(superAdmin())->get('/inventory/suppliers')->assertOk();
    $this->actingAs(superAdmin())->get('/access/users')->assertOk();
});

it('blocks a write for a user with only view permission', function () {
    $user = userWithPermissions([Permissions::SUPPLIERS_VIEW]);

    $this->actingAs($user)
        ->post('/inventory/suppliers', ['code' => 'S-1', 'company_name' => 'Acme'])
        ->assertForbidden();

    expect(Supplier::query()->count())->toBe(0);
});

it('blocks deletion for a user without the delete permission', function () {
    $supplier = Supplier::factory()->create();
    $user = userWithPermissions([Permissions::SUPPLIERS_VIEW, Permissions::SUPPLIERS_UPDATE]);

    $this->actingAs($user)
        ->delete("/inventory/suppliers/{$supplier->id}")
        ->assertForbidden();

    expect(Supplier::query()->whereKey($supplier->id)->exists())->toBeTrue();
});

it('prevents a user from granting a role whose permissions they do not hold', function () {
    $actor = userWithPermissions([
        Permissions::USERS_VIEW,
        Permissions::USERS_CREATE,
        Permissions::USERS_UPDATE,
        Permissions::ROLES_UPDATE,
    ]);

    Permission::findOrCreate(Permissions::PRODUCTS_DELETE, 'web');
    $privileged = Role::findOrCreate('Privileged', 'web');
    $privileged->syncPermissions([Permissions::PRODUCTS_DELETE]);

    $target = User::factory()->create();

    $this->actingAs($actor)
        ->from("/access/users/{$target->id}")
        ->put("/access/users/{$target->id}", ['roles' => ['Privileged']])
        ->assertSessionHasErrors('roles');

    expect($target->refresh()->hasRole('Privileged'))->toBeFalse();
});

it('prevents anyone but a super admin from granting super admin', function () {
    $actor = userWithPermissions(Permissions::all());
    Role::findOrCreate(Roles::SUPER_ADMIN, 'web');
    $target = User::factory()->create();

    $this->actingAs($actor)
        ->from("/access/users/{$target->id}")
        ->put("/access/users/{$target->id}", ['roles' => [Roles::SUPER_ADMIN]])
        ->assertSessionHasErrors('roles');

    expect($target->refresh()->hasRole(Roles::SUPER_ADMIN))->toBeFalse();
});

it('lets a super admin grant any role', function () {
    Role::findOrCreate(Roles::ADMIN, 'web');
    $target = User::factory()->create();

    $this->actingAs(superAdmin())
        ->put("/access/users/{$target->id}", ['roles' => [Roles::ADMIN]])
        ->assertSessionHasNoErrors();

    expect($target->refresh()->hasRole(Roles::ADMIN))->toBeTrue();
});

it('refuses to remove the last super admin', function () {
    $admin = superAdmin();

    $this->actingAs($admin)
        ->from("/access/users/{$admin->id}")
        ->put("/access/users/{$admin->id}", ['roles' => []])
        ->assertSessionHasErrors('roles');

    expect($admin->refresh()->hasRole(Roles::SUPER_ADMIN))->toBeTrue();
});
