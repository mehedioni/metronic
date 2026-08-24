<?php

use App\Models\User;

it('redirects guests away from protected pages', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows the login screen to guests', function () {
    $this->get('/login')->assertOk();
});

it('signs a user in with valid credentials', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('refuses to sign in a deactivated account', function () {
    $user = User::factory()->create([
        'password' => 'password123',
        'is_active' => false,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('terminates the session of a user deactivated mid-session', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    $user->update(['is_active' => false]);

    $this->get('/dashboard')->assertForbidden();
});

it('signs a user out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('sends a password reset link', function () {
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status');
});

it('lets a signed-in user change their own password', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->actingAs($user)
        ->put('/password', [
            'current_password' => 'password123',
            'password' => 'new-password456',
            'password_confirmation' => 'new-password456',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password456', $user->refresh()->password))->toBeTrue();
});
