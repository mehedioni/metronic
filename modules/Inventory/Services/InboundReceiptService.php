<?php

namespace Modules\Inventory\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\InboundReceiptStatus;
use Modules\Inventory\Exceptions\RestrictedDeletionException;
use Modules\Inventory\Models\InboundReceipt;
use Modules\Inventory\Support\DocumentNumberGenerator;
use Modules\Inventory\Support\QuerySorter;

class InboundReceiptService
{
    public function __construct(private DocumentNumberGenerator $numbers) {}

    private const SORTABLE = [
        'reference_number' => 'reference_number',
        'status' => 'status',
        'source' => 'source',
        'received_date' => 'received_date',
        'created_at' => 'created_at',
    ];

    /**
     * @param  array{search?: string|null, status?: string|null, source?: string|null, supplier_id?: string|null, from?: string|null, to?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, InboundReceipt>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return InboundReceipt::query()
            ->with(['supplier:id,company_name', 'receivedBy:id,name'])
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($filters['supplier_id'] ?? null, fn ($query, $supplier) => $query->where('supplier_id', $supplier))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('received_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('received_date', '<=', $to))
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
    public function create(array $data, ?int $userId = null): InboundReceipt
    {
        return DB::transaction(function () use ($data, $userId): InboundReceipt {
            $receipt = InboundReceipt::create([
                ...collect($data)->except('items')->all(),
                'reference_number' => $data['reference_number']
                    ?? $this->numbers->generate(InboundReceipt::class, 'reference_number', 'inbound_receipt'),
                'received_by' => $data['received_by'] ?? $userId,
            ]);

            $this->replaceItems($receipt, $data['items'] ?? []);

            return $receipt->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InboundReceipt $receipt, array $data): InboundReceipt
    {
        $this->assertEditable($receipt);

        return DB::transaction(function () use ($receipt, $data): InboundReceipt {
            $receipt->update(collect($data)->except('items')->all());

            if (array_key_exists('items', $data)) {
                $this->replaceItems($receipt, $data['items']);
            }

            return $receipt->refresh()->load('items');
        });
    }

    public function delete(InboundReceipt $receipt): void
    {
        $this->assertEditable($receipt);

        $receipt->delete();
    }

    /**
     * A receipt that already moved stock owns ledger rows; editing it would
     * make that history lie, so it must be cancelled instead.
     */
    private function assertEditable(InboundReceipt $receipt): void
    {
        if ($receipt->isProcessed() || ! $receipt->status->isEditable()) {
            throw RestrictedDeletionException::because(
                "Receipt \"{$receipt->reference_number}\"",
                'it is '.$receipt->status->value.' and owns stock history',
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceItems(InboundReceipt $receipt, array $items): void
    {
        $receipt->items()->delete();

        foreach ($items as $item) {
            $receipt->items()->create($item);
        }
    }

    /**
     * Statuses a receipt may be moved to from its current one, for the UI.
     *
     * @return array<int, string>
     */
    public function allowedTransitions(InboundReceipt $receipt): array
    {
        return array_map(
            fn (InboundReceiptStatus $status): string => $status->value,
            $receipt->status->allowedTransitions(),
        );
    }
}
