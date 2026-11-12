<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = $this->filteredQuery($request)
            ->with(['category', 'brand'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request)
    {
        $products = $this->filteredQuery($request)->with(['category', 'brand'])->orderBy('name')->get();

        $filename = 'inventory_report_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Name', 'Category', 'Brand', 'Quantity', 'Unit Price', 'Total Value', 'Supplier', 'Stock Status']);

            foreach ($products as $p) {
                fputcsv($handle, [
                    $p->sku,
                    $p->name,
                    $p->category?->name,
                    $p->brand?->name,
                    $p->quantity,
                    number_format($p->unit_price, 2),
                    number_format($p->quantity * $p->unit_price, 2),
                    $p->supplier,
                    $p->stock_status,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): View
    {
        return view('products.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        DB::transaction(function () use ($validated, $request) {
            $product = Product::create($validated);

            if ($product->quantity > 0) {
                $this->recordMovement($product, 'stock_in', $product->quantity, 'Opening stock', $request);
            }
        });

        return redirect()
            ->route('products.index')
            ->with('success', "Product \"{$validated['name']}\" was added.");
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'brand']);

        $transactions = $product->transactions()->with('user')->latest()->limit(10)->get();

        return view('products.show', compact('product', 'transactions'));
    }

    public function edit(Product $product): View
    {
        $transactions = $product->transactions()->with('user')->latest()->limit(5)->get();

        return view('products.edit', ['product' => $product, 'transactions' => $transactions] + $this->formOptions());
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        // Quantity changes go through adjustStock() so every change has a reason and a user.
        $product->update($this->validateProduct($request, $product->id, false));

        return redirect()
            ->route('products.index')
            ->with('success', "Product \"{$product->name}\" was updated.");
    }

    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:stock_in,stock_out'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ], [
            'type.required' => 'Choose Stock In or Stock Out.',
            'quantity.required' => 'Enter how many units to add or remove.',
            'quantity.min' => 'The quantity must be at least 1.',
        ]);

        DB::transaction(function () use ($validated, $product, $request) {
            $current = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($validated['type'] === 'stock_out' && $validated['quantity'] > $current->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$current->quantity} in stock, so you cannot remove {$validated['quantity']}.",
                ]);
            }

            $validated['type'] === 'stock_in'
                ? $current->increment('quantity', $validated['quantity'])
                : $current->decrement('quantity', $validated['quantity']);

            $this->recordMovement(
                $current,
                $validated['type'],
                (int) $validated['quantity'],
                $validated['reference'] ?: 'Manual adjustment',
                $request
            );
        });

        $verb = $validated['type'] === 'stock_in' ? 'added to' : 'removed from';

        return redirect()
            ->route('products.edit', $product)
            ->with('success', "{$validated['quantity']} unit(s) {$verb} {$product->name}.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', "Product \"{$name}\" was deleted.");
    }

    private function formOptions(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
        ];
    }

    private function recordMovement(Product $product, string $type, int $quantity, string $reference, Request $request): void
    {
        InventoryTransaction::create([
            'product_id' => $product->id,
            'user_id' => $request->user()?->id,
            'type' => $type,
            'quantity' => $quantity,
            'reference' => $reference,
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = Product::query();

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category_id', (int) $request->input('category'));
        }

        if ($request->filled('brand') && $request->input('brand') !== 'all') {
            $query->where('brand_id', (int) $request->input('brand'));
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

    private function validateProduct(Request $request, ?int $ignoreId = null, bool $withQuantity = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required', 'string', 'max:50',
                'unique:products,sku' . ($ignoreId ? ",{$ignoreId}" : ''),
            ],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'quantity' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
        ];

        if (! $withQuantity) {
            unset($rules['quantity']);
        }

        return $request->validate($rules, [
            'sku.unique' => 'That SKU is already in use by another product.',
            'category_id.required' => 'Choose a category for this product.',
            'category_id.exists' => 'That category no longer exists.',
            'brand_id.exists' => 'That brand no longer exists.',
            'quantity.min' => 'Quantity cannot be negative.',
            'reorder_level.min' => 'Reorder level cannot be negative.',
            'unit_price.min' => 'Unit price cannot be negative.',
        ]);
    }
}
