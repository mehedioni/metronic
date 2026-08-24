<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Actions\CreateShipmentAction;
use Modules\Inventory\Actions\DispatchShipmentAction;
use Modules\Inventory\Actions\TransitionShipmentAction;
use Modules\Inventory\Enums\ShipmentStatus;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Http\Requests\StoreShipmentRequest;
use Modules\Inventory\Http\Requests\TransitionShipmentRequest;
use Modules\Inventory\Http\Requests\UpdateShipmentRequest;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Shipment;
use Modules\Inventory\Services\ShipmentService;

class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $shipments) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', Shipment::class);

        return Inertia::render('Inventory::Shipments/Index', [
            'shipments' => $this->shipments->paginate($request->filters()),
            'filters' => $request->filters(),
            'statuses' => ShipmentStatus::values(),
        ]);
    }

    public function show(Shipment $shipment): Response
    {
        $this->authorize('view', $shipment);

        return Inertia::render('Inventory::Shipments/Show', [
            'shipment' => $shipment->load([
                'order:id,order_number,customer_name,status',
                'items.orderItem.product:id,name,sku',
                'items.orderItem.variant:id,sku,name',
            ]),
            'allowedTransitions' => array_map(
                fn (ShipmentStatus $status): string => $status->value,
                $shipment->status->allowedTransitions(),
            ),
        ]);
    }

    /**
     * Create a shipment for an order. No stock moves until it is dispatched.
     */
    public function store(StoreShipmentRequest $request, Order $order, CreateShipmentAction $create): RedirectResponse
    {
        $this->authorize('create', Shipment::class);

        $this->authorize('create', Shipment::class);

        $shipment = $create->handle($order, $request->validated(), $request->user()->id);

        return redirect()
            ->route('inventory.shipments.show', $shipment)
            ->with('success', 'Shipment created.');
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->authorize('update', $shipment);

        $this->shipments->update($shipment, $request->validated());

        return back()->with('success', 'Shipment updated.');
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        $this->authorize('delete', $shipment);

        $this->shipments->delete($shipment);

        return redirect()
            ->route('inventory.shipments.index')
            ->with('success', 'Shipment deleted.');
    }

    /**
     * Dispatch: deduct stock, release the reservation, advance the order.
     */
    public function dispatchShipment(Request $request, Shipment $shipment, DispatchShipmentAction $dispatch): RedirectResponse
    {
        $this->authorize('dispatch', $shipment);

        $dispatch->handle($shipment, $request->user()->id);

        return back()->with('success', 'Shipment dispatched and stock deducted.');
    }

    public function transition(TransitionShipmentRequest $request, Shipment $shipment, TransitionShipmentAction $transition): RedirectResponse
    {
        $this->authorize('update', $shipment);

        $transition->handle(
            $shipment,
            ShipmentStatus::from($request->validated()['status']),
            $request->user()->id,
        );

        return back()->with('success', 'Shipment updated.');
    }
}
