<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\InboundReceiptStatus;
use Modules\Inventory\Exceptions\AlreadyProcessedException;
use Modules\Inventory\Exceptions\InvalidStatusTransitionException;
use Modules\Inventory\Models\InboundReceipt;
use Modules\Inventory\Models\InboundReceiptItem;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\MovementContext;

/**
 * Moves a receipt into "received" and adds its items to stock.
 *
 * Idempotency: the receipt row is locked and "processed_at" re-checked inside
 * the transaction, so two concurrent calls cannot both write movements.
 */
class ReceiveInboundReceiptAction
{
    public function __construct(private InventoryService $inventory) {}

    public function handle(InboundReceipt $receipt, ?int $userId = null): InboundReceipt
    {
        return DB::transaction(function () use ($receipt, $userId): InboundReceipt {
            $locked = InboundReceipt::query()
                ->whereKey($receipt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isProcessed()) {
                throw AlreadyProcessedException::for("Receipt {$locked->reference_number}");
            }

            if (! $locked->status->canTransitionTo(InboundReceiptStatus::Received)) {
                throw InvalidStatusTransitionException::between(
                    $locked->status->value,
                    InboundReceiptStatus::Received->value,
                );
            }

            $movementType = $locked->source->movementType();

            $locked->load('items');

            foreach ($locked->items as $item) {
                $this->inventory->record(
                    $item->unit(),
                    $movementType,
                    $item->quantity,
                    new MovementContext(
                        supplierId: $locked->supplier_id,
                        reference: $locked,
                        reason: "Received on {$locked->reference_number}",
                        unitCost: $this->unitCost($item),
                        userId: $userId,
                    ),
                );
            }

            $locked->forceFill([
                'status' => InboundReceiptStatus::Received,
                'processed_at' => Carbon::now(),
                'received_date' => $locked->received_date ?? Carbon::now()->toDateString(),
                'received_by' => $locked->received_by ?? $userId,
            ])->save();

            return $locked->refresh();
        });
    }

    private function unitCost(InboundReceiptItem $item): ?float
    {
        return $item->unit_cost === null ? null : (float) $item->unit_cost;
    }
}
