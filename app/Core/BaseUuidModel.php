<?php

namespace App\Core;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base model for domain tables that use UUID primary keys but keep no
 * deleted_at column — pivots, line items and append-only ledgers, whose
 * lifetime is owned by their parent record.
 *
 * Domain aggregates that need recovery/audit should extend BaseModel instead.
 */
abstract class BaseUuidModel extends Model
{
    use HasUuids;

    /**
     * The primary key type.
     */
    protected $keyType = 'string';

    /**
     * Indicates the primary key is not auto-incrementing.
     */
    public $incrementing = false;
}
