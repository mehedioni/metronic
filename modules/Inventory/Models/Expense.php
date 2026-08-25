<?php

namespace Modules\Inventory\Models;

use App\Core\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Inventory\Database\Factories\ExpenseFactory;
use Modules\Inventory\Enums\ExpenseCategory;
use Modules\Inventory\Policies\ExpensePolicy;

/**
 * An operating expense, recorded against the trading day it belongs to.
 */
#[Fillable([
    'spent_on', 'category', 'amount', 'currency', 'reference',
    'supplier_id', 'description', 'created_by',
])]
#[UsePolicy(ExpensePolicy::class)]
#[UseFactory(ExpenseFactory::class)]
class Expense extends BaseModel
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    protected $attributes = [
        'currency' => 'USD',
        'category' => ExpenseCategory::Other->value,
    ];

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'category' => ExpenseCategory::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Store the trading day as a plain date.
     *
     * The "date" cast alone writes a full datetime, because Eloquent formats
     * every date attribute with the model's date format. MySQL truncates that
     * back to a DATE column, but SQLite keeps the string verbatim — and then
     * "spent_on <= '2026-08-25'" is a string comparison that a stored
     * "2026-08-25 00:00:00" loses. Writing the date itself behaves the same on
     * both engines.
     */
    public function setSpentOnAttribute(mixed $value): void
    {
        $this->attributes['spent_on'] = $value === null
            ? null
            : Carbon::parse($value)->toDateString();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner
                ->where('description', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%"),
        ));
    }

    /**
     * Expenses that fall on a trading day inside the range, inclusive.
     */
    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where('spent_on', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('spent_on', '<=', $to));
    }
}
