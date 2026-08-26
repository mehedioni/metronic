<?php

namespace App\Core\Services;

use App\Core\Concerns\HasAvatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The single photo on a record: set it, replace it, remove it.
 *
 * Every model using the HasAvatar trait goes through here, so there is one
 * upload path rather than one per module. Knows nothing about disks or URLs —
 * that is FileStorageService's job.
 */
class AvatarService
{
    public function __construct(private FileStorageService $files) {}

    /**
     * Store a new photo, replacing whichever the record had.
     *
     * @param  Model&object{avatar_disk: ?string, avatar_path: ?string}  $model
     */
    public function put(Model $model, UploadedFile $file): Model
    {
        $this->assertSupported($model);

        // Written before the transaction opens: a failed upload should not
        // roll back rows, and a rolled-back row only leaves an orphaned file,
        // which is the recoverable direction.
        $stored = $this->files->store($file, $this->directoryFor($model));
        $previousPath = $model->avatar_path;
        $previousDisk = $model->avatar_disk;

        return DB::transaction(function () use ($model, $stored, $previousPath, $previousDisk): Model {
            $model->forceFill([
                'avatar_disk' => $stored->disk,
                'avatar_path' => $stored->path,
            ])->save();

            // The bytes it replaced go only once this commit succeeds.
            $this->files->deleteAfterCommit($previousPath, $previousDisk);

            return $model->refresh();
        });
    }

    /**
     * Remove the photo, leaving the record itself alone.
     *
     * @param  Model&object{avatar_disk: ?string, avatar_path: ?string}  $model
     */
    public function clear(Model $model): Model
    {
        $this->assertSupported($model);

        if (blank($model->avatar_path)) {
            return $model;
        }

        $path = $model->avatar_path;
        $disk = $model->avatar_disk;

        return DB::transaction(function () use ($model, $path, $disk): Model {
            $model->forceFill(['avatar_disk' => null, 'avatar_path' => null])->save();

            $this->files->deleteAfterCommit($path, $disk);

            return $model->refresh();
        });
    }

    /**
     * Apply whatever the request asked for: a new photo, its removal, or
     * neither. Saves every caller repeating the same three branches.
     *
     * @param  Model&object{avatar_disk: ?string, avatar_path: ?string}  $model
     */
    public function sync(Model $model, ?UploadedFile $file, bool $remove = false): Model
    {
        if ($file instanceof UploadedFile) {
            return $this->put($model, $file);
        }

        return $remove ? $this->clear($model) : $model;
    }

    /**
     * Photos are filed per record, so one record's photos can be removed
     * without touching another's.
     */
    private function directoryFor(Model $model): string
    {
        return $this->files->path($model->avatarPathKey(), $model->getKey());
    }

    private function assertSupported(Model $model): void
    {
        if (! in_array(HasAvatar::class, class_uses_recursive($model), true)) {
            throw new \InvalidArgumentException(
                $model::class.' does not use the HasAvatar trait.',
            );
        }
    }
}
