<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One stored setting. Read through App\Core\Services\SettingsService, which
 * caches the whole table — nothing should query this model directly.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];
}
