<?php

namespace Modules\Inventory\Models;

use App\Core\BaseModel;
use App\Core\Concerns\HasAvatar;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\CustomerFactory;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Policies\CustomerPolicy;

/**
 * A customer of the store. Each order also keeps its own copy of the name,
 * email and phone: the customer record is the current contact detail, the
 * order is what those details were at the time it was placed.
 */
#[Fillable([
    'code', 'name', 'email', 'phone', 'address_line1', 'address_line2',
    'city', 'state', 'postal_code', 'country', 'tax_number', 'notes', 'status',
])]
#[UsePolicy(CustomerPolicy::class)]
#[UseFactory(CustomerFactory::class)]
class Customer extends BaseModel
{
    use HasAvatar;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => RecordStatus::Active->value,
    ];

    /**
     * The photo's URL travels with the record, so no screen builds one.
     *
     * @var list<string>
     */
    protected $appends = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Orders that count towards what this customer has actually spent —
     * cancelled ones never do.
     */
    public function billableOrders(): HasMany
    {
        return $this->orders()->billable();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"),
        ));
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }

    /**
     * The contact snapshot an order takes when it is placed for this customer.
     *
     * @return array<string, string|null>
     */
    public function orderSnapshot(): array
    {
        return [
            'customer_name' => $this->name,
            'customer_email' => $this->email,
            'customer_phone' => $this->phone,
        ];
    }
}
