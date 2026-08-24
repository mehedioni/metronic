<?php

namespace App\Core\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies a caller-supplied sort to a list query.
 *
 * The column is always checked against an allowlist owned by the service, so
 * a request can never order by an unindexed or unrelated column — or inject
 * one. An unknown column falls back to the service's default ordering rather
 * than erroring, because a stale bookmarked URL should still render a list.
 */
final class QuerySorter
{
    /**
     * @param  array<string, string>  $allowed  public sort key => column expression
     */
    public static function apply(
        Builder $query,
        ?string $sort,
        ?string $direction,
        array $allowed,
        string $fallbackColumn = 'created_at',
        string $fallbackDirection = 'desc',
    ): Builder {
        $column = $allowed[$sort] ?? null;
        $direction = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

        // The secondary key on the primary key keeps pagination stable when the
        // sorted column holds duplicates — without it a row can appear on two
        // pages, or on none.
        if ($column === null) {
            return $query->orderBy($fallbackColumn, $fallbackDirection)->orderBy('id');
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
