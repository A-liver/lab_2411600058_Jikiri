<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);

        $products = $query->orderBy('name')->paginate(15)->withQueryString();
        $categories = Product::select('category')->distinct()->orderBy('category')->pluck('category');

        return view('products.index', compact('products', 'categories'));
    }

    public function export(Request $request)
    {
        $products = $this->filteredQuery($request)->orderBy('name')->get();

        $filename = 'inventory_report_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Name', 'Category', 'Quantity', 'Unit Price', 'Total Value', 'Supplier', 'Stock Status']);

            foreach ($products as $p) {
                fputcsv($handle, [
                    $p->sku,
                    $p->name,
                    $p->category,
                    $p->quantity,
                    number_format($p->unit_price, 2),
                    number_format($p->quantity * $p->unit_price, 2),
                    $p->supplier,
                    $p->stock_status,
                ]);
            }

            fclose($handle);
        }, $filename);
    }

    private function filteredQuery(Request $request)
    {
        $query = Product::query();

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('stock_status') && $request->input('stock_status') !== 'all') {
            match ($request->input('stock_status')) {
                'in-stock' => $query->whereColumn('quantity', '>', 'reorder_level'),
                'low-stock' => $query->whereColumn('quantity', '<=', 'reorder_level')->where('quantity', '>', 0),
                'out-of-stock' => $query->where('quantity', '<=', 0),
                default => null,
            };
        }

        if ($request->filled('min_price')) {
            $query->where('unit_price', '>=', (float) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('unit_price', '<=', (float) $request->input('max_price'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function create(): View
    {
        return view('products.create');
    }

    /**
     * Store a newly created product. The starting quantity is recorded as an
     * opening-stock transaction so every unit has a ledger entry behind it.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);
        $openingStock = (int) $validated['quantity'];

        DB::transaction(function () use ($validated, $openingStock) {
            $product = Product::create([...$validated, 'quantity' => 0]);

            if ($openingStock > 0) {
                InventoryTransaction::record([
                    'product_id' => $product->id,
                    'user_id' => auth()->id(),
                    'type' => InventoryTransaction::TYPE_IN,
                    'quantity' => $openingStock,
                    'reference_number' => 'OPENING-STOCK',
                    'notes' => 'Initial stock when the product was created.',
                ]);
            }
        });

        return redirect()
            ->route('products.index')
            ->with('success', "Product \"{$validated['name']}\" was added successfully.");
    }

    /**
     * Display the specified product with its transaction history.
     */
    public function show(Request $request, Product $product): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $transactions = $product->transactions()
            ->with('user')
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->recent()
            ->paginate(15)
            ->withQueryString();

        return view('products.show', compact('product', 'transactions'));
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): View
    {
        return view('products.edit', compact('product'));
    }

    /**
     * Update the specified product. Quantity is ignored here: stock only
     * changes through transactions and adjustments (audit trail).
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request, $product->id);

        unset($validated['quantity']);

        $product->update($validated);

        return redirect()
            ->route('products.index')
            ->with('success', "Product \"{$product->name}\" was updated successfully.");
    }

    /**
     * Remove the specified product. Products with stock history are kept
     * so the audit trail survives.
     */
    public function destroy(Product $product): RedirectResponse
    {
        if ($product->transactions()->exists()) {
            return back()->withErrors([
                'delete' => "\"{$product->name}\" has stock movement history and cannot be deleted.",
            ]);
        }

        $name = $product->name;
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', "Product \"{$name}\" was deleted.");
    }

    /**
     * Shared validation rules for store() and update().
     */
    private function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required', 'string', 'max:50',
                'unique:products,sku' . ($ignoreId ? ",{$ignoreId}" : ''),
            ],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
        ], [
            'sku.unique' => 'That SKU is already in use by another product.',
            'quantity.min' => 'Quantity cannot be negative.',
            'reorder_level.min' => 'Reorder level cannot be negative.',
            'unit_price.min' => 'Unit price cannot be negative.',
        ]);
    }
}