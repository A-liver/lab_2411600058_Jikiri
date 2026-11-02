<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive', 'deleted'])],
            'sort' => ['nullable', Rule::in(['name', 'lead_time_days', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Supplier::query()->withCount(['products', 'purchaseOrders']);

        match ($filters['status'] ?? 'all') {
            'active' => $query->active(),
            'inactive' => $query->where('is_active', false),
            'deleted' => $query->onlyTrashed(),
            default => null,
        };

        $query->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        });

        // sort and direction were whitelisted by the rules above, so they are safe to use here
        $suppliers = $query
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        return view('suppliers.create');
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', "Supplier \"{$supplier->name}\" was added.");
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load(['products' => fn ($q) => $q->orderBy('name')]);

        $recentOrders = $supplier->purchaseOrders()->latest('order_date')->latest('id')->limit(10)->get();

        $stats = [
            'orders' => $supplier->purchaseOrders()->count(),
            'open' => $supplier->purchaseOrders()->active()->count(),
            'spent' => (float) $supplier->purchaseOrders()
                ->where('status', PurchaseOrder::STATUS_RECEIVED)->sum('total'),
            'rating' => $supplier->performance_rating,
        ];

        $availableProducts = Product::whereNotIn('id', $supplier->products->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        return view('suppliers.show', compact('supplier', 'recentOrders', 'stats', 'availableProducts'));
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', "Supplier \"{$supplier->name}\" was updated.");
    }

    /** Soft delete (admin only). Blocked while the supplier still has open purchase orders. */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('admin');

        if ($supplier->purchaseOrders()->active()->exists()) {
            return back()->withErrors([
                'delete' => "\"{$supplier->name}\" still has open purchase orders. Complete or cancel them first.",
            ]);
        }

        $supplier->delete();

        return redirect()
            ->route('suppliers.index')
            ->with('success', "Supplier \"{$supplier->name}\" was deleted. You can restore it from the Deleted filter.");
    }

    public function restore(int $id): RedirectResponse
    {
        Gate::authorize('admin');

        $supplier = Supplier::onlyTrashed()->findOrFail($id);
        $supplier->restore();

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', "Supplier \"{$supplier->name}\" was restored.");
    }

    /** Link a product to this supplier with its pricing agreement (many-to-many pivot). */
    public function attachProduct(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'supplier_sku' => ['nullable', 'string', 'max:50'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'min_order_quantity' => ['required', 'integer', 'min:1'],
            'pack_size' => ['required', 'integer', 'min:1'],
            'is_preferred' => ['nullable', 'boolean'],
        ]);

        $preferred = $request->boolean('is_preferred');

        DB::transaction(function () use ($supplier, $data, $preferred) {
            // Only one preferred supplier per product.
            if ($preferred) {
                DB::table('product_supplier')->where('product_id', $data['product_id'])->update(['is_preferred' => false]);
            }

            $supplier->products()->syncWithoutDetaching([
                $data['product_id'] => [
                    'supplier_sku' => $data['supplier_sku'] ?? null,
                    'unit_cost' => $data['unit_cost'],
                    'min_order_quantity' => $data['min_order_quantity'],
                    'pack_size' => $data['pack_size'],
                    'is_preferred' => $preferred,
                ],
            ]);
        });

        return back()->with('success', 'Product linked to this supplier.');
    }

    public function detachProduct(Supplier $supplier, Product $product): RedirectResponse
    {
        $supplier->products()->detach($product->id);

        return back()->with('success', "\"{$product->name}\" was unlinked from this supplier.");
    }
}