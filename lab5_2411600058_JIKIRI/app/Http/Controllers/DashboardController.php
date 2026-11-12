<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalProducts = Product::count();

        $lowStockCount = Product::whereColumn('quantity', '<=', 'reorder_level')
            ->where('quantity', '>', 0)
            ->count();

        $outOfStockCount = Product::where('quantity', '<=', 0)->count();

        // In stock = total - low stock - out of stock
        $inStockCount = $totalProducts - $lowStockCount - $outOfStockCount;

        $totalValue = (float) Product::sum(DB::raw('quantity * unit_price'));

        // LOW STOCK (exclude out of stock)
        $lowStockProducts = Product::whereColumn('quantity', '<=', 'reorder_level')
            ->where('quantity', '>', 0)
            ->orderBy('quantity')
            ->limit(6)
            ->get();

        // OUT OF STOCK
        $outOfStockProducts = Product::where('quantity', '<=', 0)
            ->orderBy('name')
            ->get();

        $stockByCategory = $this->stockBreakdown(Category::class, 'category_id');
        $stockByBrand = $this->stockBreakdown(Brand::class, 'brand_id', 'No brand');

        $recentProducts = Product::with('category')->latest('updated_at')->limit(6)->get();

        $productsForCharts = Product::with('category')->get()->map(fn ($p) => [
            'name' => $p->name,
            'category' => $p->category?->name ?? 'Uncategorised',
            'quantity' => $p->quantity,
            'unitPrice' => (float) $p->unit_price,
            'reorderLevel' => $p->reorder_level,
            ]);

        return view('dashboard.index', compact(
            'totalProducts',
            'lowStockCount',
            'outOfStockCount',
            'inStockCount', 
            'totalValue',
            'lowStockProducts',
            'outOfStockProducts',
            'recentProducts',
            'stockByCategory',
            'stockByBrand',
            'productsForCharts' 

        ));
    }

    /**
     * Stock totals per category or brand: products, units, value, low and out of stock.
     * Products without a brand are grouped in one extra row when $emptyLabel is given.
     */
    private function stockBreakdown(string $model, string $foreignKey, ?string $emptyLabel = null)
    {
        $table = (new $model)->getTable();

        $aggregates = [
            'COUNT(products.id) as product_count',
            'COALESCE(SUM(products.quantity), 0) as units',
            'COALESCE(SUM(products.quantity * products.unit_price), 0) as stock_value',
            'COALESCE(SUM(CASE WHEN products.quantity > 0 AND products.quantity <= products.reorder_level THEN 1 ELSE 0 END), 0) as low_count',
            'COALESCE(SUM(CASE WHEN products.id IS NOT NULL AND products.quantity <= 0 THEN 1 ELSE 0 END), 0) as out_count',
        ];

        $rows = $model::query()
            ->leftJoin('products', "products.{$foreignKey}", '=', "{$table}.id")
            ->select("{$table}.id", "{$table}.name")
            ->selectRaw(implode(', ', $aggregates))
            ->groupBy("{$table}.id", "{$table}.name")
            ->orderByDesc('stock_value')
            ->orderBy("{$table}.name")
            ->get();

        if ($emptyLabel) {
            $none = Product::query()
                ->whereNull($foreignKey)
                ->selectRaw(str_replace('products.id IS NOT NULL AND ', '', implode(', ', $aggregates)))
                ->first();

            if ($none && $none->product_count > 0) {
                $none->id = null;
                $none->name = $emptyLabel;
                $rows->push($none);
            }
        }

        return $rows;
    }
}
