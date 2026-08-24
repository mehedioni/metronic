<?php

use App\Core\Support\Permissions;
use App\Models\User;

it('lists users with search and pagination', function () {
    User::factory()->create(['name' => 'Alice Findable']);
    User::factory()->count(3)->create();

    $this->actingAs(userWithPermissions([Permissions::USERS_VIEW]))
        ->get('/access/users?search=Findable')
        ->assertOk();
});

it('creates a user', function () {
    $actor = userWithPermissions([Permissions::USERS_VIEW, Permissions::USERS_CREATE]);

    $this->actingAs($actor)->post('/access/users', [
        'name' => 'New Person',
        'email' => 'new.person@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'new.person@example.com')->exists())->toBeTrue();
});

it('validates required user fields', function () {
    $actor = userWithPermissions([Permissions::USERS_VIEW, Permissions::USERS_CREATE]);

    $this->actingAs($actor)
        ->post('/access/users', ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

it('deactivates and reactivates a user', function () {
    $actor = userWithPermissions([Permissions::USERS_VIEW, Permissions::USERS_UPDATE]);
    $target = User::factory()->create();

    $this->actingAs($actor)->patch("/access/users/{$target->id}/status")->assertSessionHasNoErrors();
    expect($target->refresh()->is_active)->toBeFalse();

    $this->actingAs($actor)->patch("/access/users/{$target->id}/status");
    expect($target->refresh()->is_active)->toBeTrue();
});

it('refuses to let a user deactivate themselves', function () {
    $actor = userWithPermissions([Permissions::USERS_VIEW, Permissions::USERS_UPDATE]);

    $this->actingAs($actor)
        ->patch("/access/users/{$actor->id}/status")
        ->assertForbidden();

    expect($actor->refresh()->is_active)->toBeTrue();
});
