<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ProcurementWidgets
{
    private const PREFIX = 'procurement.widget.';
    private const KEYS = ['pending', 'recent', 'performance', 'reorder'];

    public function __construct(private ReorderService $reorder) {}

    /** Forget every cached widget (called whenever the underlying data changes). */
    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget(self::PREFIX . $key);
        }
    }

    /** Orders waiting for approval. */
    public function pendingOrders(): array
    {
        return Cache::remember(self::PREFIX . 'pending', 60, function () {
            $threshold = (float) config('procurement.approval_threshold', 1000);
            $oldest = PurchaseOrder::pending()->min('created_at');

            return [
                'count' => PurchaseOrder::pending()->count(),
                'total_value' => round((float) PurchaseOrder::pending()->sum('total'), 2),
                // High-value orders that already have their first-level approval.
                'awaiting_final' => PurchaseOrder::pending()
                    ->where('total', '>=', $threshold)
                    ->whereHas('approvals', fn ($q) => $q->where('action', 'approved'))
                    ->count(),
                'oldest_days' => $oldest ? (int) Carbon::parse($oldest)->diffInDays(now()) : null,
            ];
        });
    }

    /** The five most recent purchase orders. */
    public function recentOrders(): array
    {
        return Cache::remember(self::PREFIX . 'recent', 60, function () {
            return PurchaseOrder::with('supplier')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'reference' => $order->reference_number,
                    'supplier' => $order->supplier->name,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'status_color' => $order->status_color,
                    'total' => (float) $order->total,
                    'date' => $order->order_date->format('Y-m-d'),
                    'url' => route('purchase-orders.show', $order, false),   // relative link
                ])
                ->all();
        });
    }

    /** Top suppliers by on-time delivery (one grouped query, no N+1). */
    public function supplierPerformance(): array
    {
        return Cache::remember(self::PREFIX . 'performance', 120, function () {
            $stats = PurchaseOrder::query()
                ->where('status', PurchaseOrder::STATUS_RECEIVED)
                ->whereNotNull('received_date')
                ->whereNotNull('expected_delivery_date')
                ->groupBy('supplier_id')
                ->selectRaw('supplier_id, COUNT(*) as orders, SUM(received_date <= expected_delivery_date) as on_time, SUM(total) as spent')
                ->get();

            $names = Supplier::withTrashed()->whereIn('id', $stats->pluck('supplier_id'))->pluck('name', 'id');

            return $stats
                ->map(fn ($row) => [
                    'supplier_id' => $row->supplier_id,
                    'name' => $names[$row->supplier_id] ?? 'Unknown',
                    'orders' => (int) $row->orders,
                    'on_time_pct' => round($row->on_time / $row->orders * 100, 1),
                    'spent' => round((float) $row->spent, 2),
                    'url' => route('suppliers.show', $row->supplier_id, false),
                ])
                ->sort(fn ($a, $b) => [$b['on_time_pct'], $b['orders']] <=> [$a['on_time_pct'], $a['orders']])
                ->take(5)
                ->values()
                ->all();
        });
    }

    /** Products at or below their reorder point (uses the step 6 ReorderService). */
    public function reorderAlerts(): array
    {
        return Cache::remember(self::PREFIX . 'reorder', 120, function () {
            $rows = $this->reorder->getProductsNeedingReorder();

            return [
                'count' => $rows->count(),
                'without_supplier' => $rows->filter(fn ($row) => $row['supplier'] === null)->count(),
                'drafted' => $rows->filter(fn ($row) => $row['in_draft'] > 0)->count(),
                
                'items' => $rows->take(6)->map(fn ($row) => [
                    'name' => $row['product']->name,
                    'sku' => $row['product']->sku,
                    'stock' => $row['product']->quantity,
                    'reorder_point' => $row['reorder_point'],
                    'quantity' => $row['quantity'],
                    'in_draft' => $row['in_draft'],
                    'supplier' => $row['supplier']?->name ?? 'No active supplier',
                    'url' => route('products.show', $row['product'], false),
                ])->all(),
            ];
        });
    }
}