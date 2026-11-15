<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        Product::create($validated);

        return redirect()
            ->route('products.index')
            ->with('success', "Product \"{$validated['name']}\" was added.");
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'brand']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        return view('products.edit', ['product' => $product] + $this->formOptions());
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validateProduct($request, $product->id, true));

        return redirect()
            ->route('products.index')
            ->with('success', "Product \"{$product->name}\" was updated.");
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
