<?php

use App\Core\Services\SettingsService;
use App\Core\Support\Currency;
use App\Core\Support\Permissions;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Inventory\Models\Order;

beforeEach(function () {
    Storage::fake('public');
    config()->set('files.disk', 'public');

    $this->settings = app(SettingsService::class);
    $this->settings->flush();
});

it('falls back to configuration until a setting is saved', function () {
    config()->set('app.name', 'Config Store');
    config()->set('currencies.default', 'USD');

    expect($this->settings->companyName())->toBe('Config Store')
        ->and($this->settings->currencyCode())->toBe('USD')
        ->and($this->settings->logoUrl())->toBeNull();
});

it('saves the store name and currency', function () {
    $manager = userWithPermissions([Permissions::SETTINGS_MANAGE]);

    $this->actingAs($manager)
        ->put('/settings/general', [
            'company_name' => "Bob's Shoes Store",
            'currency' => 'BDT',
        ])
        ->assertSessionHasNoErrors();

    $this->settings->flush();

    expect($this->settings->companyName())->toBe("Bob's Shoes Store")
        ->and($this->settings->currencyCode())->toBe('BDT')
        ->and($this->settings->currency()['symbol'])->toBe('৳');
});

it('refuses a currency the application is not configured for', function () {
    $manager = userWithPermissions([Permissions::SETTINGS_MANAGE]);

    $this->actingAs($manager)
        ->put('/settings/general', ['company_name' => 'Store', 'currency' => 'XYZ'])
        ->assertSessionHasErrors('currency');
});

it('keeps store settings behind the settings permission', function () {
    $staff = userWithPermissions([Permissions::PRODUCTS_VIEW]);

    $this->actingAs($staff)
        ->put('/settings/general', ['company_name' => 'Hijacked', 'currency' => 'USD'])
        ->assertForbidden();

    $this->settings->flush();

    expect($this->settings->companyName())->not->toBe('Hijacked');
});

it('stores the logo and serves it from the configured disk', function () {
    $manager = userWithPermissions([Permissions::SETTINGS_MANAGE]);

    $this->actingAs($manager)
        ->put('/settings/general', [
            'company_name' => 'Store',
            'currency' => 'USD',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])
        ->assertSessionHasNoErrors();

    $this->settings->flush();
    $path = $this->settings->get(SettingsService::LOGO_PATH);

    expect($path)->toStartWith('settings/')
        ->and($this->settings->logoUrl())->toContain($path);

    Storage::disk('public')->assertExists($path);
});

it('replaces the logo and removes the bytes it replaced', function () {
    $manager = userWithPermissions([Permissions::SETTINGS_MANAGE]);

    $save = fn (UploadedFile $logo) => $this->actingAs($manager)->put('/settings/general', [
        'company_name' => 'Store',
        'currency' => 'USD',
        'logo' => $logo,
    ]);

    $save(UploadedFile::fake()->image('first.png'))->assertSessionHasNoErrors();
    $this->settings->flush();
    $first = $this->settings->get(SettingsService::LOGO_PATH);

    $save(UploadedFile::fake()->image('second.png'))->assertSessionHasNoErrors();
    $this->settings->flush();
    $second = $this->settings->get(SettingsService::LOGO_PATH);

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertExists($second);
    Storage::disk('public')->assertMissing($first);
});

it('removes the logo when asked, and leaves it alone otherwise', function () {
    $manager = userWithPermissions([Permissions::SETTINGS_MANAGE]);

    $this->actingAs($manager)->put('/settings/general', [
        'company_name' => 'Store',
        'currency' => 'USD',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $this->settings->flush();
    $path = $this->settings->get(SettingsService::LOGO_PATH);

    // A save that sends no file is not a request to delete the logo.
    $this->actingAs($manager)->put('/settings/general', [
        'company_name' => 'Store Renamed',
        'currency' => 'USD',
    ]);

    $this->settings->flush();
    expect($this->settings->get(SettingsService::LOGO_PATH))->toBe($path);

    $this->actingAs($manager)->put('/settings/general', [
        'company_name' => 'Store Renamed',
        'currency' => 'USD',
        'remove_logo' => true,
    ]);

    $this->settings->flush();

    expect($this->settings->get(SettingsService::LOGO_PATH))->toBeNull()
        ->and($this->settings->logoUrl())->toBeNull();

    Storage::disk('public')->assertMissing($path);
});

it('lets any signed-in user edit their own name', function () {
    $staff = userWithPermissions([]);

    $this->actingAs($staff)
        ->put('/settings/profile', ['name' => 'Renamed Person'])
        ->assertSessionHasNoErrors();

    expect($staff->refresh()->name)->toBe('Renamed Person');
});

it('never lets a user move their own login', function () {
    $staff = userWithPermissions([]);
    $original = $staff->email;

    $this->actingAs($staff)->put('/settings/profile', [
        'name' => 'Renamed Person',
        'email' => 'someone.else@example.com',
    ]);

    expect($staff->refresh()->email)->toBe($original);
});

it('changes a password only with the current one', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $this->actingAs($user)
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-9!',
            'password_confirmation' => 'new-password-9!',
        ])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($user)
        ->put('/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-9!',
            'password_confirmation' => 'new-password-9!',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password-9!', $user->refresh()->password))->toBeTrue();
});

it('shares the store settings with every page', function () {
    $manager = userWithPermissions([Permissions::SETTINGS_MANAGE, Permissions::DASHBOARD_VIEW]);

    $this->actingAs($manager)->put('/settings/general', [
        'company_name' => 'Shared Store',
        'currency' => 'EUR',
    ]);

    $this->actingAs($manager)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('app.name', 'Shared Store')
            ->where('settings.companyName', 'Shared Store')
            ->where('settings.currency.code', 'EUR')
            ->where('settings.currency.symbol', '€')
            // The choices the form offers come from configuration.
            ->has('currencies', count(Currency::all())));
});

it('keeps no currency of its own on an order', function () {
    // A single store trades in one currency, so a per-record copy would only
    // ever restate the setting — and could contradict it.
    expect(Order::factory()->create()->getAttributes())->not->toHaveKey('currency');
});
