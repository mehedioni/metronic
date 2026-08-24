<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Actions\CancelInboundReceiptAction;
use Modules\Inventory\Actions\ReceiveInboundReceiptAction;
use Modules\Inventory\Enums\InboundReceiptStatus;
use Modules\Inventory\Enums\InboundSource;
use Modules\Inventory\Http\Requests\CancelRequest;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Http\Requests\StoreInboundReceiptRequest;
use Modules\Inventory\Http\Requests\UpdateInboundReceiptRequest;
use Modules\Inventory\Models\InboundReceipt;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\InboundReceiptService;

class InboundReceiptController extends Controller
{
    public function __construct(private InboundReceiptService $receipts) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', InboundReceipt::class);

        return Inertia::render('Inventory::Inbound/Index', [
            'receipts' => $this->receipts->paginate($request->filters()),
            'filters' => $request->filters(),
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreInboundReceiptRequest $request): RedirectResponse
    {
        $this->authorize('create', InboundReceipt::class);

        $receipt = $this->receipts->create($request->validated(), $request->user()->id);

        return redirect()
            ->route('inventory.inbound.show', $receipt)
            ->with('success', 'Receipt created.');
    }

    public function show(InboundReceipt $receipt): Response
    {
        $this->authorize('view', $receipt);

        return Inertia::render('Inventory::Inbound/Show', [
            'receipt' => $receipt->load(['supplier:id,company_name', 'receivedBy:id,name', 'items.product:id,name,sku', 'items.variant:id,sku,name']),
            'allowedTransitions' => $this->receipts->allowedTransitions($receipt),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateInboundReceiptRequest $request, InboundReceipt $receipt): RedirectResponse
    {
        $this->authorize('update', $receipt);

        $this->receipts->update($receipt, $request->validated());

        return back()->with('success', 'Receipt updated.');
    }

    public function destroy(InboundReceipt $receipt): RedirectResponse
    {
        $this->authorize('delete', $receipt);

        $this->receipts->delete($receipt);

        return redirect()
            ->route('inventory.inbound.index')
            ->with('success', 'Receipt deleted.');
    }

    /**
     * Post the receipt to stock.
     */
    public function receive(Request $request, InboundReceipt $receipt, ReceiveInboundReceiptAction $receive): RedirectResponse
    {
        $this->authorize('receive', $receipt);

        $receive->handle($receipt, $request->user()->id);

        return back()->with('success', 'Stock received.');
    }

    public function cancel(CancelRequest $request, InboundReceipt $receipt, CancelInboundReceiptAction $cancel): RedirectResponse
    {
        $this->authorize('cancel', $receipt);

        $cancel->handle($receipt, $request->validated()['reason'] ?? null, $request->user()->id);

        return back()->with('success', 'Receipt cancelled.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::query()->active()->select(['id', 'company_name'])->orderBy('company_name')->get(),
            'products' => Product::query()->active()->select(['id', 'name', 'sku', 'type'])->with('variants:id,product_id,sku,name')->orderBy('name')->get(),
            'sources' => InboundSource::values(),
            'statuses' => InboundReceiptStatus::values(),
        ];
    }
}
