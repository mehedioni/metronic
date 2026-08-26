<?php

namespace App\Core\Support;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The result of storing a file: everything a caller needs to persist a
 * reference to it, and nothing about how it was stored.
 *
 * The disk travels with the path deliberately. When the configured disk later
 * changes from "public" to "s3", rows written before the switch still say
 * where their bytes actually are, so old files keep resolving without a
 * migration.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class StoredFile implements Arrayable
{
    public function __construct(
        public string $disk,
        public string $path,
        public ?string $originalName = null,
        public ?string $mimeType = null,
        public ?int $size = null,
    ) {}

    /**
     * Attributes ready to merge into a model's fillable data.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
        ];
    }
}
