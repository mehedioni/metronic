<?php

namespace App\Core\Services;

use App\Core\Support\StoredFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The one place the application touches stored files.
 *
 * Callers name a logical folder and hand over an upload; they never name a
 * disk, build a URL, or know whether the bytes landed on local disk, S3 or
 * anything else. That is what makes switching provider a config change rather
 * than a search-and-replace:
 *
 *     FILES_DISK=public  ->  FILES_DISK=s3
 *
 * Two rules hold the abstraction together:
 *
 *  - only the relative path is ever persisted, never a full URL, so the same
 *    row resolves against whichever disk is configured later;
 *  - a row may carry the disk it was written to, so files stored before a
 *    provider switch keep resolving afterwards.
 */
class FileStorageService
{
    /**
     * Store an upload under a logical path and return its reference.
     *
     * Options:
     *   disk      write somewhere other than the configured disk
     *   name      an explicit filename, extension included
     *   visibility  'public' | 'private', when the driver supports it
     *
     * @param  array<string, mixed>  $options
     */
    public function store(UploadedFile $file, string $path, array $options = []): StoredFile
    {
        $disk = (string) ($options['disk'] ?? $this->disk());
        $directory = $this->normalisePath($path);
        $filename = (string) ($options['name'] ?? $this->uniqueName($file));

        $stored = $this->filesystem($disk)->putFileAs(
            $directory,
            $file,
            $filename,
            array_filter(['visibility' => $options['visibility'] ?? null]),
        );

        if ($stored === false) {
            throw new RuntimeException("Could not store [{$filename}] on disk [{$disk}].");
        }

        return new StoredFile(
            disk: $disk,
            path: $stored,
            originalName: $file->getClientOriginalName(),
            mimeType: $file->getClientMimeType(),
            size: $file->getSize() === false ? null : $file->getSize(),
        );
    }

    /**
     * Store raw contents — a generated PDF, an export — under a logical path.
     *
     * @param  array<string, mixed>  $options
     */
    public function put(string $contents, string $path, string $filename, array $options = []): StoredFile
    {
        $disk = (string) ($options['disk'] ?? $this->disk());
        $target = $this->normalisePath($path).'/'.$this->sanitiseFilename($filename);

        $this->filesystem($disk)->put(
            $target,
            $contents,
            array_filter(['visibility' => $options['visibility'] ?? null]),
        );

        return new StoredFile(
            disk: $disk,
            path: $target,
            originalName: $filename,
            size: strlen($contents),
        );
    }

    /**
     * A URL the browser can load, or null when the disk cannot serve one.
     *
     * Signed URLs come first when configured, which is what a private disk
     * needs. Otherwise the disk has to be publicly addressable: a local disk
     * with no "url" in its config is not, and Laravel's local driver will
     * happily invent "/storage/..." for it — a link that 404s. Returning null
     * lets a page render a placeholder instead of a broken image.
     */
    public function url(?string $path, ?string $disk = null): ?string
    {
        if (blank($path)) {
            return null;
        }

        $name = $disk ?? $this->disk();
        $filesystem = $this->filesystem($name);

        if (config('files.signed_urls')) {
            $signed = $this->trySignedUrl($filesystem, $path);

            if ($signed !== null) {
                return $signed;
            }
        }

        if (! $this->servesUrls($name)) {
            return null;
        }

        try {
            return $filesystem->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether a disk can hand out a public URL at all.
     *
     * A local disk needs an explicit "url"; anything else (S3 and friends)
     * builds one from its own configuration.
     */
    private function servesUrls(string $disk): bool
    {
        $config = (array) config("filesystems.disks.{$disk}", []);

        if (filled($config['url'] ?? null)) {
            return true;
        }

        return ($config['driver'] ?? null) !== 'local';
    }

    /**
     * An expiring URL, for a file that should not be publicly addressable.
     */
    public function temporaryUrl(?string $path, ?int $minutes = null, ?string $disk = null): ?string
    {
        if (blank($path)) {
            return null;
        }

        $minutes ??= (int) config('files.signed_url_minutes', 15);

        return $this->trySignedUrl($this->filesystem($disk ?? $this->disk()), $path, $minutes);
    }

    public function exists(?string $path, ?string $disk = null): bool
    {
        return filled($path) && $this->filesystem($disk ?? $this->disk())->exists($path);
    }

    /**
     * Delete a file, tolerating one that has already gone.
     */
    public function delete(?string $path, ?string $disk = null): bool
    {
        if (blank($path)) {
            return false;
        }

        $filesystem = $this->filesystem($disk ?? $this->disk());

        if (! $filesystem->exists($path)) {
            return false;
        }

        return $filesystem->delete($path);
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    public function deleteMany(array $paths, ?string $disk = null): void
    {
        foreach ($paths as $path) {
            $this->delete($path, $disk);
        }
    }

    /**
     * Delete a file only once the surrounding transaction commits.
     *
     * Replacing an image means writing a row and removing bytes. Deleting
     * eagerly loses the old file if the transaction then rolls back, leaving a
     * row pointing at nothing. Deferring to after commit means the worst case
     * is an orphaned file, which is recoverable; the reverse is not.
     */
    public function deleteAfterCommit(?string $path, ?string $disk = null): void
    {
        if (blank($path)) {
            return;
        }

        DB::afterCommit(fn () => $this->delete($path, $disk));
    }

    public function size(?string $path, ?string $disk = null): ?int
    {
        return $this->exists($path, $disk)
            ? $this->filesystem($disk ?? $this->disk())->size($path)
            : null;
    }

    /**
     * The disk the application is configured to use right now.
     */
    public function disk(): string
    {
        return (string) config('files.disk', 'public');
    }

    /**
     * A configured logical root, e.g. path('products', $id, 'images').
     */
    public function path(string $key, int|string ...$segments): string
    {
        $root = (string) config("files.paths.{$key}", $key);

        return $this->normalisePath(implode('/', [$root, ...$segments]));
    }

    private function filesystem(string $disk): Filesystem
    {
        return Storage::disk($disk);
    }

    /**
     * A signed URL, or null when the driver does not support one.
     */
    private function trySignedUrl(Filesystem $filesystem, string $path, ?int $minutes = null): ?string
    {
        $minutes ??= (int) config('files.signed_url_minutes', 15);

        try {
            return $filesystem->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A collision-proof filename that keeps the original extension.
     *
     * The client's own name is never used on disk: it may collide, carry a
     * path, or be crafted to look executable. It is kept in the database
     * instead, where it is only ever displayed.
     */
    private function uniqueName(UploadedFile $file): string
    {
        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');

        return Str::uuid()->toString().'.'.preg_replace('/[^a-z0-9]/', '', $extension);
    }

    private function sanitiseFilename(string $filename): string
    {
        $name = Str::of($filename)->basename()->trim();

        return $name->isEmpty() ? Str::uuid()->toString() : (string) $name;
    }

    /**
     * Strip leading, trailing and doubled slashes so a caller's path cannot
     * escape its folder or produce an empty segment.
     */
    private function normalisePath(string $path): string
    {
        $clean = preg_replace('#/+#', '/', str_replace(['..', '\\'], ['', '/'], $path));

        return trim((string) $clean, '/');
    }
}
