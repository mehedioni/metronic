<?php

namespace Modules\Inventory\Support;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The configured order lifecycle, read from config/orders.php.
 *
 * Resolved once per request and memoised: the statuses are configuration, so
 * re-reading and re-hydrating them on every model access would be waste.
 */
final class OrderStatuses
{
    /** @var array<int, OrderStatus>|null */
    private static ?array $byId = null;

    /** @var array<string, OrderStatus>|null */
    private static ?array $byKey = null;

    /**
     * @return array<int, OrderStatus>
     */
    public static function all(): array
    {
        self::hydrate();

        return array_values(self::$byId);
    }

    /**
     * @return Collection<int, OrderStatus>
     */
    public static function collect(): Collection
    {
        return new Collection(self::all());
    }

    public static function find(int $id): OrderStatus
    {
        self::hydrate();

        return self::$byId[$id]
            ?? throw new InvalidArgumentException("Unknown order status id [{$id}].");
    }

    public static function tryFind(?int $id): ?OrderStatus
    {
        if ($id === null) {
            return null;
        }

        self::hydrate();

        return self::$byId[$id] ?? null;
    }

    public static function key(string $key): OrderStatus
    {
        self::hydrate();

        return self::$byKey[$key]
            ?? throw new InvalidArgumentException("Unknown order status [{$key}].");
    }

    public static function tryKey(?string $key): ?OrderStatus
    {
        if ($key === null) {
            return null;
        }

        self::hydrate();

        return self::$byKey[$key] ?? null;
    }

    /**
     * Accepts whatever a caller happens to hold: an id, a key, or a status.
     */
    public static function resolve(OrderStatus|int|string|null $status): ?OrderStatus
    {
        return match (true) {
            $status instanceof OrderStatus => $status,
            is_int($status) => self::tryFind($status),
            is_string($status) => ctype_digit($status)
                ? self::tryFind((int) $status)
                : self::tryKey($status),
            default => null,
        };
    }

    public static function default(): OrderStatus
    {
        return self::key((string) config('orders.default', 'draft'));
    }

    /**
     * The status a quote is written down as.
     */
    public static function quote(): OrderStatus
    {
        return self::key((string) config('orders.quote', 'draft'));
    }

    /**
     * Statuses a form may set directly. Everything else is reached only
     * through an action that carries the inventory effect with it.
     *
     * @return array<int, OrderStatus>
     */
    public static function assignable(): array
    {
        return array_values(array_filter(array_map(
            fn (string $key): ?OrderStatus => self::tryKey($key),
            (array) config('orders.assignable', []),
        )));
    }

    /**
     * @return array<int, int>
     */
    public static function ids(): array
    {
        return array_map(fn (OrderStatus $status): int => $status->id, self::all());
    }

    /**
     * @return array<int, int>
     */
    public static function assignableIds(): array
    {
        return array_map(fn (OrderStatus $status): int => $status->id, self::assignable());
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_map(fn (OrderStatus $status): string => $status->key, self::all());
    }

    /**
     * Every status flagged as holding a reservation, for the queries that ask
     * "which orders are promising stock right now".
     *
     * @return array<int, int>
     */
    public static function reservingIds(): array
    {
        return array_map(
            fn (OrderStatus $status): int => $status->id,
            array_filter(self::all(), fn (OrderStatus $status): bool => $status->holdsReservation),
        );
    }

    /**
     * Ids that count as trade — every status not flagged void.
     *
     * @return array<int, int>
     */
    public static function billableIds(): array
    {
        return array_values(array_map(
            fn (OrderStatus $status): int => $status->id,
            array_filter(self::all(), fn (OrderStatus $status): bool => ! $status->isVoid()),
        ));
    }

    /**
     * @return array<int, int>
     */
    public static function voidIds(): array
    {
        return array_values(array_map(
            fn (OrderStatus $status): int => $status->id,
            array_filter(self::all(), fn (OrderStatus $status): bool => $status->isVoid()),
        ));
    }

    /**
     * Forget the memoised statuses — used by tests that swap the config.
     */
    public static function flush(): void
    {
        self::$byId = null;
        self::$byKey = null;
    }

    private static function hydrate(): void
    {
        if (self::$byId !== null) {
            return;
        }

        self::$byId = [];
        self::$byKey = [];

        foreach ((array) config('orders.statuses', []) as $config) {
            $status = OrderStatus::fromConfig($config);

            self::$byId[$status->id] = $status;
            self::$byKey[$status->key] = $status;
        }
    }
}
