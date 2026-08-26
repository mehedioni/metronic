<?php

use App\Core\Services\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->files = app(FileStorageService::class);
});

it('stores an upload on the configured disk and returns a relative path', function () {
    Storage::fake('public');
    config()->set('files.disk', 'public');

    $stored = $this->files->store(
        UploadedFile::fake()->image('photo.jpg'),
        'products/7/images',
    );

    expect($stored->disk)->toBe('public')
        ->and($stored->path)->toStartWith('products/7/images/')
        // A path, never a URL: that is what makes the row survive a provider change.
        ->and($stored->path)->not->toContain('http')
        ->and($stored->originalName)->toBe('photo.jpg')
        ->and($stored->size)->toBeGreaterThan(0);

    Storage::disk('public')->assertExists($stored->path);
});

it('never writes the client filename to disk', function () {
    Storage::fake('public');
    config()->set('files.disk', 'public');

    $stored = $this->files->store(
        UploadedFile::fake()->image('evil name.jpg'),
        'products/1/images',
    );

    expect(basename($stored->path))->toEndWith('.jpg')
        ->and(basename($stored->path))->not->toContain(' ')
        ->and($stored->path)->toStartWith('products/1/images/')
        // The original is kept for display only.
        ->and($stored->originalName)->toContain('evil name');
});

it('gives two uploads of the same name different paths', function () {
    Storage::fake('public');
    config()->set('files.disk', 'public');

    $first = $this->files->store(UploadedFile::fake()->image('a.jpg'), 'products/1/images');
    $second = $this->files->store(UploadedFile::fake()->image('a.jpg'), 'products/1/images');

    expect($first->path)->not->toBe($second->path);
    Storage::disk('public')->assertExists($first->path);
    Storage::disk('public')->assertExists($second->path);
});

it('builds the URL from whichever disk is configured', function () {
    Storage::fake('public');
    config()->set('files.disk', 'public');

    expect($this->files->url('products/7/images/abc.jpg'))
        ->toContain('products/7/images/abc.jpg');
});

it('resolves a path against the disk it was written to, not the current one', function () {
    Storage::fake('public');
    Storage::fake('s3');

    $stored = $this->files->store(
        UploadedFile::fake()->image('old.jpg'),
        'products/1/images',
        ['disk' => 'public'],
    );

    // The application later switches provider.
    config()->set('files.disk', 's3');

    // A row that recorded its disk still resolves, with no migration.
    expect($this->files->url($stored->path, $stored->disk))->toContain($stored->path)
        ->and($this->files->exists($stored->path, $stored->disk))->toBeTrue();
});

it('returns null rather than throwing when the disk cannot build a URL', function () {
    // Laravel's "local" disk has no url key; a page should render a
    // placeholder instead of a 500.
    config()->set('files.disk', 'local');

    expect($this->files->url('products/1/images/a.jpg'))->toBeNull();
});

it('returns null for a blank path', function () {
    expect($this->files->url(null))->toBeNull()
        ->and($this->files->url(''))->toBeNull();
});

it('deletes a stored file and tolerates one already gone', function () {
    Storage::fake('public');
    config()->set('files.disk', 'public');

    $stored = $this->files->store(UploadedFile::fake()->image('x.jpg'), 'products/1/images');

    expect($this->files->delete($stored->path))->toBeTrue();
    Storage::disk('public')->assertMissing($stored->path);

    // Deleting twice is not an error.
    expect($this->files->delete($stored->path))->toBeFalse()
        ->and($this->files->delete(null))->toBeFalse();
});

it('keeps a caller from escaping its folder', function () {
    Storage::fake('public');
    config()->set('files.disk', 'public');

    $stored = $this->files->store(
        UploadedFile::fake()->image('a.jpg'),
        'products/../../etc',
    );

    expect($stored->path)->not->toContain('..')
        ->and($stored->path)->toStartWith('products/etc/');
});

it('builds logical paths from configuration', function () {
    config()->set('files.paths.products', 'catalogue');

    expect($this->files->path('products', 25, 'images'))->toBe('catalogue/25/images')
        // An unconfigured key falls back to its own name.
        ->and($this->files->path('unknown'))->toBe('unknown');
});

it('reports the configured disk', function () {
    config()->set('files.disk', 's3');

    expect($this->files->disk())->toBe('s3');
});

it('stores raw contents too', function () {
    Storage::fake('public');
    config()->set('files.disk', 'public');

    $stored = $this->files->put('id,name', 'exports', 'products.csv');

    expect($stored->path)->toBe('exports/products.csv')
        ->and($stored->size)->toBe(7);

    Storage::disk('public')->assertExists('exports/products.csv');
});
