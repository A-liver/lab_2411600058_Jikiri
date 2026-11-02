<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderWorkflow;
use Closure;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\ReorderService;
use App\Services\SearchService;

class PurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private PurchaseOrderWorkflow $workflow, private SearchService $search) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(PurchaseOrder::STATUSES))],
            'supplier_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:50'],
            'auto' => ['nullable', Rule::in(['0', '1'])],
            'min_total' => ['nullable', 'numeric', 'min:0'],
            'max_total' => ['nullable', 'numeric', 'min:0', 'gte:min_total'],
        ]);

        $orders = $this->search->purchaseOrders($filters)
            ->with(['supplier', 'createdBy'])
            ->latest('order_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $suppliers = Supplier::withTrashed()->orderBy('name')->pluck('name', 'id')->all();
        $statuses = PurchaseOrder::STATUSES;

        return view('purchase-orders.index', compact('orders', 'suppliers', 'statuses'));
    }

    public function create(): View
    {
        $this->authorize('create', PurchaseOrder::class);

        return view('purchase-orders.create', $this->formData());
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $submit = ($data['action'] ?? 'draft') === 'submit';

        $order = DB::transaction(function () use ($data, $request) {
            $order = PurchaseOrder::create([
                'reference_number' => PurchaseOrder::generateReferenceNumber(),
                'supplier_id' => $data['supplier_id'],
                'status' => PurchaseOrder::STATUS_DRAFT,
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'tax_rate' => $data['tax_rate'],
                'terms' => $data['terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $order->items()->createMany($this->itemRows($data['items']));
            $order->calculateTotal();

            return $order;
        });

        return $submit
            ? $this->submitAfterSave($order, $request, "Purchase order {$order->reference_number} was submitted for approval.")
            : redirect()->route('purchase-orders.show', $order)
                ->with('success', "Purchase order {$order->reference_number} was saved as a draft.");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'createdBy', 'approvedBy', 'items.product', 'approvals.user', 'inventoryTransactions.product', 'inventoryTransactions.user']);

        return view('purchase-orders.show', ['order' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        $this->authorize('view', $purchaseOrder);

        if (! $purchaseOrder->canBeEdited()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->withErrors(['edit' => 'Only draft purchase orders can be edited.']);
        }

        $this->authorize('update', $purchaseOrder);

        $purchaseOrder->load('items');

        return view('purchase-orders.edit', array_merge($this->formData($purchaseOrder), ['purchaseOrder' => $purchaseOrder]));
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->canBeEdited()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->withErrors(['edit' => 'Only draft purchase orders can be edited.']);
        }

        $data = $request->validated();
        $submit = ($data['action'] ?? 'draft') === 'submit';

        DB::transaction(function () use ($data, $purchaseOrder) {
            $purchaseOrder->update([
                'supplier_id' => $data['supplier_id'],
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'tax_rate' => $data['tax_rate'],
                'terms' => $data['terms'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $purchaseOrder->items()->delete();
            $purchaseOrder->items()->createMany($this->itemRows($data['items']));
            $purchaseOrder->calculateTotal();
        });

        return $submit
            ? $this->submitAfterSave($purchaseOrder, $request, "Purchase order {$purchaseOrder->reference_number} was submitted for approval.")
            : redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('success', "Purchase order {$purchaseOrder->reference_number} was updated.");
    }
    /** Admins delete drafts (lab rule). Everything else that is still open is cancelled. */
    public function destroy(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $reference = $purchaseOrder->reference_number;

        if ($purchaseOrder->status === PurchaseOrder::STATUS_DRAFT && $request->user()->can('delete', $purchaseOrder)) {
            $purchaseOrder->delete();

            return redirect()->route('purchase-orders.index')->with('success', "Draft {$reference} was deleted.");
        }

        $this->authorize('cancel', $purchaseOrder);

        return $this->run(
            fn () => $this->workflow->cancel($purchaseOrder, $request->user()),
            "Purchase order {$reference} was cancelled.",
            $purchaseOrder
        );
    }

    // ---------- workflow actions ----------

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('submit', $purchaseOrder);

        return $this->run(
            fn () => $this->workflow->submit($purchaseOrder, $request->user()),
            "Purchase order {$purchaseOrder->reference_number} was submitted for approval.",
            $purchaseOrder
        );
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('approve', $purchaseOrder);
        $request->validate(['comment' => ['nullable', 'string', 'max:500']]);

        try {
            $final = $this->workflow->approve($purchaseOrder, $request->user(), $request->input('comment'));
        } catch (DomainException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', $final
            ? "Purchase order {$purchaseOrder->reference_number} was approved."
            : 'First-level approval recorded. This order is above $'
                . number_format((float) config('procurement.approval_threshold'), 2) . ' and still needs an admin\'s approval.');
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('reject', $purchaseOrder);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return $this->run(
            fn () => $this->workflow->reject($purchaseOrder, $request->user(), $data['reason']),
            "Purchase order {$purchaseOrder->reference_number} was rejected.",
            $purchaseOrder
        );
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('send', $purchaseOrder);

        try {
            $emailed = $this->workflow->send($purchaseOrder, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        $supplier = $purchaseOrder->supplier->name;

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', $emailed
            ? "Purchase order {$purchaseOrder->reference_number} was emailed to {$supplier}."
            : "Purchase order {$purchaseOrder->reference_number} was marked as sent, but no email was sent because {$supplier} has no email address on file.");
    }

    public function reopen(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('reopen', $purchaseOrder);

        return $this->run(
            fn () => $this->workflow->reopen($purchaseOrder, $request->user()),
            "Purchase order {$purchaseOrder->reference_number} is a draft again. Make your changes and resubmit.",
            $purchaseOrder
        );
    }
    public function reorderCheck(ReorderService $reorder): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $result = $reorder->generatePurchaseOrders();
        $skipped = $result['skipped']->count();
        $warning = $skipped ? " {$skipped} product(s) were skipped because they have no active supplier." : '';

        if ($result['orders']->isEmpty()) {
            return back()->with('success', 'Reorder check complete: nothing new to order.' . $warning);
        }

        return redirect()
            ->route('purchase-orders.index', ['status' => 'draft'])
            ->with('success', 'Reorder check created ' . $result['orders']->count() . ' draft purchase order(s).' . $warning);
    }
    public function receiveForm(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('receive', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'items.product']);

        return view('purchase-orders.receive', ['order' => $purchaseOrder]);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('receive', $purchaseOrder);

        $data = $request->validate([
            'received' => ['required', 'array'],
            'received.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'delivery_note' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $complete = $this->workflow->receive(
                $purchaseOrder,
                $request->user(),
                $data['received'],
                $data['delivery_note'] ?? null,
                $data['notes'] ?? null
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['workflow' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', $complete
            ? "Purchase order {$purchaseOrder->reference_number} was fully received. Stock levels have been updated."
            : 'Partial receipt recorded and stock levels updated. The remaining items are still outstanding.');
    }

    // ---------- helpers ----------

    private function run(Closure $action, string $success, PurchaseOrder $order): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $order)->with('success', $success);
    }
    /** Saves are always drafts; submitting goes through the workflow so checks, history and emails stay in one place. */
    private function submitAfterSave(PurchaseOrder $order, Request $request, string $success): RedirectResponse
    {
        try {
            $this->workflow->submit($order, $request->user());
        } catch (DomainException $e) {
            return redirect()->route('purchase-orders.show', $order)
                ->withErrors(['workflow' => "Saved as a draft, but it could not be submitted: {$e->getMessage()}"]);
        }

        return redirect()->route('purchase-orders.show', $order)->with('success', $success);
    }

    private function itemRows(array $items): array
    {
        return collect($items)->map(fn ($i) => [
            'product_id' => (int) $i['product_id'],
            'quantity' => (int) $i['quantity'],
            'unit_price' => (float) $i['unit_price'],
        ])->values()->all();
    }

    private function formData(?PurchaseOrder $order = null): array
    {
        $suppliers = Supplier::query()
            ->where(function ($q) use ($order) {
                $q->where('is_active', true);
                if ($order) {
                    $q->orWhere('id', $order->supplier_id);
                }
            })
            ->with(['products' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        $catalog = $suppliers->mapWithKeys(fn ($s) => [
            $s->id => $s->products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'stock' => $p->quantity,
                'unit_cost' => (float) $p->pivot->unit_cost,
                'min_qty' => (int) $p->pivot->min_order_quantity,
                'pack_size' => (int) $p->pivot->pack_size,
            ])->values(),
        ]);

        $supplierMeta = $suppliers->mapWithKeys(fn ($s) => [
            $s->id => [
                'lead_time_days' => $s->lead_time_days,
                'payment_terms' => $s->payment_terms,
                'minimum_order_amount' => (float) $s->minimum_order_amount,
            ],
        ]);

        $initialItems = $order
            ? $order->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'quantity' => $i->quantity,
                'unit_price' => (float) $i->unit_price,
            ])->values()->all()
            : [];

        return compact('suppliers', 'catalog', 'supplierMeta', 'initialItems');
    }
    
    
}