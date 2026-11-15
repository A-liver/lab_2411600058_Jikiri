<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\TransactionResource;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends Controller
{
    private function meta(array $extra = []): array
    {
        return ['generated_at' => now()->toIso8601String()] + $extra;
    }

    public function stats(): JsonResponse
    {
        $row = Product::query()
            ->selectRaw('COUNT(*) as total_products')
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total_value')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity > 0 AND quantity <= reorder_level THEN 1 ELSE 0 END), 0) as low_stock')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock')
            ->first();

        $total = (int) $row->total_products;
        $low = (int) $row->low_stock;
        $out = (int) $row->out_of_stock;

        return response()->json([
            'data' => [
                'total_products' => $total,
                'total_value' => round((float) $row->total_value, 2),
                'low_stock' => $low,
                'out_of_stock' => $out,
                'in_stock' => $total - $low - $out,
            ],
            'meta' => $this->meta(),
        ]);
    }

    /** Everything at or below its reorder level, most urgent first. */
    public function lowStock(): ProductResource|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $total = Product::atReorderLevel()->count();

        $products = Product::atReorderLevel()
            ->orderBy('quantity')
            ->orderBy('name')
            ->limit(8)
            ->get();

        return ProductResource::collection($products)
            ->additional(['meta' => $this->meta(['total' => $total])]);
    }

    public function quantity(Product $product): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'quantity' => $product->quantity,
                'reorder_level' => $product->reorder_level,
            ],
            'meta' => $this->meta(),
        ]);
    }

    public function recentTransactions(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $transactions = InventoryTransaction::with(['product', 'user'])->recent()->limit(8)->get();

        return TransactionResource::collection($transactions)->additional(['meta' => $this->meta()]);
    }
}