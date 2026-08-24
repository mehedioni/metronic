<?php

namespace Modules\Inventory\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Exceptions\RestrictedDeletionException;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Support\QuerySorter;

class SupplierService
{
    private const SORTABLE = [
        'company_name' => 'company_name',
        'code' => 'code',
        'contact_name' => 'contact_name',
        'country' => 'country',
        'status' => 'status',
        'created_at' => 'created_at',
    ];

    /**
     * @param  array{search?: string|null, status?: string|null, country?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Supplier::query()
            ->withCount(['primaryProducts', 'inboundReceipts'])
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['country'] ?? null, fn ($query, $country) => $query->where('country', $country))
            ->tap(fn ($query) => QuerySorter::apply(
                $query,
                $filters['sort'] ?? null,
                $filters['direction'] ?? null,
                self::SORTABLE,
            ))
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->refresh();
    }

    public function setStatus(Supplier $supplier, RecordStatus $status): Supplier
    {
        $supplier->update(['status' => $status]);

        return $supplier->refresh();
    }

    /**
     * Suppliers with receiving history are never removed — the history has to
     * keep resolving. They are deactivated instead.
     */
    public function delete(Supplier $supplier): void
    {
        if ($supplier->inboundReceipts()->exists() || $supplier->stockMovements()->exists()) {
            throw RestrictedDeletionException::because(
                "Supplier \"{$supplier->company_name}\"",
                'it has receiving history that must stay valid',
            );
        }

        $supplier->delete();
    }

    /**
     * Aggregates the future supplier detail screen needs: what we buy from
     * them, what has been received, and when.
     *
     * @return array<string, mixed>
     */
    public function history(Supplier $supplier): array
    {
        $supplier->loadCount(['primaryProducts', 'inboundReceipts']);

        return [
            'products_supplied' => $supplier->products()
                ->select(['products.id', 'products.name', 'products.sku'])
                ->limit(50)
                ->get(),
            'total_received_quantity' => (int) $supplier->stockMovements()->inbound()->sum('quantity'),
            'last_received_at' => $supplier->inboundReceipts()
                ->whereNotNull('processed_at')
                ->max('processed_at'),
            'recent_receipts' => $supplier->inboundReceipts()
                ->with('items:id,inbound_receipt_id,quantity')
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
}
