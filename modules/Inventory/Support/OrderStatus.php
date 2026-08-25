<?php

namespace Modules\Inventory\Support;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One order status, as configured in config/orders.php.
 *
 * Replaces what used to be an enum: the lifecycle is data now, so a store can
 * add or relabel a status without a deploy. What the domain still needs are
 * the *meanings* — whether the order can be edited, whether it holds a
 * reservation, whether it can be fulfilled — so those travel with the status
 * rather than being inferred from its name.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class OrderStatus implements Arrayable, JsonSerializable
{
    /**
     * @param  array<int, string>  $transitions  keys this status may move to
     */
    public function __construct(
        public int $id,
        public string $key,
        public string $label,
        public string $variant,
        public bool $editable,
        public bool $holdsReservation,
        public bool $fulfillable,
        public bool $cancellable,
        public bool $void,
        public array $transitions,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            id: (int) $config['id'],
            key: (string) $config['key'],
            label: (string) ($config['label'] ?? ucfirst((string) $config['key'])),
            variant: (string) ($config['variant'] ?? 'neutral'),
            editable: (bool) ($config['editable'] ?? false),
            holdsReservation: (bool) ($config['holds_reservation'] ?? false),
            fulfillable: (bool) ($config['fulfillable'] ?? false),
            cancellable: (bool) ($config['cancellable'] ?? false),
            void: (bool) ($config['void'] ?? false),
            transitions: array_values((array) ($config['transitions'] ?? [])),
        );
    }

    public function is(self|string|int $other): bool
    {
        return match (true) {
            $other instanceof self => $this->id === $other->id,
            is_int($other) => $this->id === $other,
            default => $this->key === $other,
        };
    }

    /**
     * Items and totals may only change while the order has no inventory
     * impact.
     */
    public function isEditable(): bool
    {
        return $this->editable;
    }

    /**
     * True once the order holds a stock reservation.
     */
    public function holdsReservation(): bool
    {
        return $this->holdsReservation;
    }

    /**
     * Statuses from which the order's lines may still be handed over, which is
     * what turns the reservation into an on-hand deduction.
     */
    public function isFulfillable(): bool
    {
        return $this->fulfillable;
    }

    public function isCancellable(): bool
    {
        return $this->cancellable;
    }

    /**
     * A void order was called off. It never counts as trade, so revenue,
     * margins and customer spend all exclude it.
     */
    public function isVoid(): bool
    {
        return $this->void;
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return array_values(array_filter(array_map(
            fn (string $key): ?self => OrderStatuses::tryKey($key),
            $this->transitions,
        )));
    }

    public function canTransitionTo(self|string $target): bool
    {
        $key = $target instanceof self ? $target->key : $target;

        return in_array($key, $this->transitions, true);
    }

    /**
     * Sent to the frontend, which renders the label and colours the badge by
     * variant without knowing the lifecycle.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'variant' => $this->variant,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
