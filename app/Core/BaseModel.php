<?php

namespace App\Core;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base model for domain models (Core and module-owned).
 *
 * Uses UUID primary keys instead of auto-incrementing integers so records
 * are safe to expose in URLs/APIs without enumeration, and soft deletes so
 * domain data can be recovered/audited. Not used by App\Models\User, which
 * follows Laravel's default auth conventions instead.
 */
abstract class BaseModel extends BaseUuidModel
{
    use SoftDeletes;
}
