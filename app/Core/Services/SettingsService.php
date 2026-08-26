<?php

namespace App\Core\Services;

use App\Core\Models\Setting;
use App\Core\Support\Currency;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Store-wide settings: the company name, its logo, and the currency it trades
 * in.
 *
 * Read on every request, written rarely, so the whole table is cached as one
 * blob and the cache is dropped on write. Each accessor falls back to
 * configuration, which means a fresh install works before anything has been
 * saved — and honours the rule that the project name comes from config and
 * env until an operator overrides it here.
 */
class SettingsService
{
    public const CACHE_KEY = 'settings';

    public const COMPANY_NAME = 'company_name';

    public const CURRENCY = 'currency';

    public const LOGO_DISK = 'logo_disk';

    public const LOGO_PATH = 'logo_path';

    public function __construct(private FileStorageService $files) {}

    /**
     * Every stored setting, keyed by name.
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            // Resilient on purpose: shared with every Inertia response, so a
            // missing table during install must not break the whole app.
            fn (): array => rescue(fn (): array => Setting::query()->pluck('value', 'key')->all(), [], false),
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Write a batch of settings. A null value removes the setting, so it falls
     * back to configuration again rather than being stored as an empty string.
     *
     * @param  array<string, string|null>  $values
     */
    public function set(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                if ($value === null) {
                    Setting::query()->where('key', $key)->delete();

                    continue;
                }

                Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
        });

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The name the store trades under, falling back to config('app.name').
     */
    public function companyName(): string
    {
        $stored = $this->get(self::COMPANY_NAME);

        return filled($stored) ? (string) $stored : (string) config('app.name');
    }

    /**
     * The active currency code. An operator can only choose a configured one,
     * but a code removed from config later still resolves — see Currency.
     */
    public function currencyCode(): string
    {
        $stored = $this->get(self::CURRENCY);

        return Currency::supports($stored)
            ? strtoupper((string) $stored)
            : (string) config('currencies.default', 'USD');
    }

    /**
     * @return array{code: string, name: string, symbol: string, position: string, decimals: int}
     */
    public function currency(): array
    {
        return Currency::resolve($this->currencyCode());
    }

    public function logoUrl(): ?string
    {
        return $this->files->url(
            $this->get(self::LOGO_PATH),
            $this->get(self::LOGO_DISK),
        );
    }

    /**
     * Replace the logo, removing whichever file it replaced.
     */
    public function putLogo(UploadedFile $file): void
    {
        // Written before the settings change, so a failed upload leaves the
        // existing logo in place rather than a row pointing at nothing.
        $stored = $this->files->store($file, $this->files->path('settings'));
        $previousPath = $this->get(self::LOGO_PATH);
        $previousDisk = $this->get(self::LOGO_DISK);

        $this->set([
            self::LOGO_DISK => $stored->disk,
            self::LOGO_PATH => $stored->path,
        ]);

        $this->files->deleteAfterCommit($previousPath, $previousDisk);
    }

    public function clearLogo(): void
    {
        $path = $this->get(self::LOGO_PATH);
        $disk = $this->get(self::LOGO_DISK);

        if (blank($path)) {
            return;
        }

        $this->set([self::LOGO_DISK => null, self::LOGO_PATH => null]);

        $this->files->deleteAfterCommit($path, $disk);
    }

    /**
     * The shape shared with every Inertia response.
     *
     * @return array<string, mixed>
     */
    public function forSharing(): array
    {
        return [
            'companyName' => $this->companyName(),
            'logoUrl' => $this->logoUrl(),
            'currency' => $this->currency(),
        ];
    }
}
