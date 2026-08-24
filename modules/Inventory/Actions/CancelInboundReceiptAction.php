<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\InboundReceiptStatus;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\InvalidStatusTransitionException;
use Modules\Inventory\Models\InboundReceipt;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\MovementContext;

/**
 * Cancels a receipt. A receipt that already added stock is reversed with
 * compensating movements rather than by editing history.
 */
class CancelInboundReceiptAction
{
    public function __construct(private InventoryService $inventory) {}

    public function handle(InboundReceipt $receipt, ?string $reason = null, ?int $userId = null): InboundReceipt
    {
        return DB::transaction(function () use ($receipt, $reason, $userId): InboundReceipt {
            $locked = InboundReceipt::query()
                ->whereKey($receipt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === InboundReceiptStatus::Cancelled) {
                throw InvalidStatusTransitionException::between(
                    InboundReceiptStatus::Cancelled->value,
                    InboundReceiptStatus::Cancelled->value,
                );
            }

            if ($locked->isProcessed()) {
                $locked->load('items');

                foreach ($locked->items as $item) {
                    $this->inventory->record(
                        $item->unit(),
                        StockMovementType::ReceiptReversal,
                        $item->quantity,
                        new MovementContext(
                            supplierId: $locked->supplier_id,
                            reference: $locked,
                            reason: $reason ?? "Cancelled receipt {$locked->reference_number}",
                            userId: $userId,
                        ),
                    );
                }
            }

            $locked->forceFill([
                'status' => InboundReceiptStatus::Cancelled,
                'cancelled_at' => Carbon::now(),
                'processed_at' => null,
                'notes' => $reason ?? $locked->notes,
            ])->save();

            return $locked->refresh();
        });
    }
}
