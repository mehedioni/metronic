<?php

use App\Core\Support\Permissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Inventory\Models\Customer;

beforeEach(function () {
    Storage::fake('public');
    config()->set('files.disk', 'public');

    $this->manager = userWithPermissions([
        Permissions::CUSTOMERS_VIEW,
        Permissions::CUSTOMERS_CREATE,
        Permissions::CUSTOMERS_UPDATE,
    ]);
});

it('stores a photo that came with the create form', function () {
    $this->actingAs($this->manager)
        ->post('/inventory/customers', [
            'name' => 'Emma Chen',
            'email' => 'emma@example.com',
            'avatar' => UploadedFile::fake()->image('emma.jpg'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $customer = Customer::where('email', 'emma@example.com')->sole();

    // Filed under the customer that owns it, and only the relative path is kept.
    expect($customer->avatar_path)->toStartWith("customers/{$customer->id}/")
        ->and($customer->avatar_disk)->toBe('public')
        ->and($customer->avatar_url)->toContain($customer->avatar_path);

    Storage::disk('public')->assertExists($customer->avatar_path);
});

it('reports no photo URL when the customer has none', function () {
    $customer = Customer::factory()->create();

    expect($customer->avatar_url)->toBeNull()
        ->and($customer->hasAvatar())->toBeFalse();
});

it('replaces a photo and removes the bytes it replaced', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->manager)
        ->put("/inventory/customers/{$customer->id}", [
            'name' => $customer->name,
            'avatar' => UploadedFile::fake()->image('first.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $first = $customer->refresh()->avatar_path;

    $this->actingAs($this->manager)
        ->put("/inventory/customers/{$customer->id}", [
            'name' => $customer->name,
            'avatar' => UploadedFile::fake()->image('second.png'),
        ])
        ->assertSessionHasNoErrors();

    $second = $customer->refresh()->avatar_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertExists($second);
    Storage::disk('public')->assertMissing($first);
});

it('clears the photo when removal is asked for', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->manager)
        ->put("/inventory/customers/{$customer->id}", [
            'name' => $customer->name,
            'avatar' => UploadedFile::fake()->image('gone.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $path = $customer->refresh()->avatar_path;

    $this->actingAs($this->manager)
        ->put("/inventory/customers/{$customer->id}", [
            'name' => $customer->name,
            'remove_avatar' => true,
        ])
        ->assertSessionHasNoErrors();

    $customer->refresh();

    expect($customer->avatar_path)->toBeNull()
        ->and($customer->avatar_disk)->toBeNull()
        ->and($customer->avatar_url)->toBeNull();

    Storage::disk('public')->assertMissing($path);
});

it('leaves the photo alone when the form sends neither a file nor a removal', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->manager)
        ->put("/inventory/customers/{$customer->id}", [
            'name' => $customer->name,
            'avatar' => UploadedFile::fake()->image('keep.jpg'),
        ]);

    $path = $customer->refresh()->avatar_path;

    $this->actingAs($this->manager)
        ->put("/inventory/customers/{$customer->id}", ['name' => 'Renamed'])
        ->assertSessionHasNoErrors();

    expect($customer->refresh()->avatar_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

it('refuses an upload that is not an image', function () {
    $this->actingAs($this->manager)
        ->post('/inventory/customers', [
            'name' => 'Bad upload',
            'email' => 'bad@example.com',
            'avatar' => UploadedFile::fake()->create('contract.pdf', 30, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');

    // The whole submission fails, so no half-made customer is left behind.
    expect(Customer::where('email', 'bad@example.com')->exists())->toBeFalse();
});
