<?php

namespace Modules\Inventory\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Builds human-readable document numbers such as GRN-202608-0007.
 *
 * The sequence is derived from the rows already created this month, and the
 * unique index on the column remains the real guarantee: on a collision the
 * caller retries and picks up the next number.
 */
class DocumentNumberGenerator
{
    /**
     * @param  class-string<Model>  $model
     */
    public function generate(string $model, string $column, string $prefixKey): string
    {
        $prefix = config("inventory.number_prefixes.{$prefixKey}", Str::upper(Str::substr($prefixKey, 0, 3)));
        $period = Carbon::now()->format('Ym');
        $stem = "{$prefix}-{$period}-";

        $sequence = $model::withTrashed()
            ->where($column, 'like', $stem.'%')
            ->count() + 1;

        return $stem.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
