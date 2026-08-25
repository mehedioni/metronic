<?php

namespace App\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base model for domain aggregates (Core and module-owned).
 *
 * Adds soft deletes so domain data can be recovered and audited. Keys are
 * Laravel's default auto-incrementing bigints, which is also what
 * App\Models\User and the Spatie tables use, so every foreign key in the
 * schema is the same shape.
 *
 * Line items, pivots and append-only ledgers extend Eloquent's Model directly:
 * they need no soft deletes, because their lifetime belongs to their parent
 * record.
 */
abstract class BaseModel extends Model
{
    use SoftDeletes;
}
