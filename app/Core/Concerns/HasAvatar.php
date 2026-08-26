<?php

namespace App\Core\Concerns;

use App\Core\Services\FileStorageService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A record with one stored photo, kept as `avatar_disk` + `avatar_path`.
 *
 * The URL is derived on read rather than stored, so it is never stale: the
 * provider can change, or the site can move to another domain, without a
 * single row being rewritten. AvatarService does the writing; this only
 * exposes what is already there.
 *
 * @phpstan-require-extends Model
 */
trait HasAvatar
{
    /**
     * Which config('files.paths') key this model's photos live under.
     */
    public function avatarPathKey(): string
    {
        return $this->getTable();
    }

    public function hasAvatar(): bool
    {
        return filled($this->avatar_path);
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => app(FileStorageService::class)->url($this->avatar_path, $this->avatar_disk),
        )->shouldCache();
    }
}
