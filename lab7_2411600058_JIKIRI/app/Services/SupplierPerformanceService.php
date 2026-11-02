<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class SupplierPerformanceService
{
    private const PLACED = [
        PurchaseOrder::STATUS_APPROVED,
        PurchaseOrder::STATUS_SENT,
        PurchaseOrder::STATUS_RECEIVED,
    ];

    /** One row per supplier that has placed orders in the period (filtered on order date). */
    public function summary(?string $from = null, ?string $to = null): Collection
    {
        $base = fn () => PurchaseOrder::query()
            ->whereIn('status', self::PLACED)
            ->when($from, fn ($q, $v) => $q->whereDate('order_date', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('order_date', '<=', $v));

        $stats = $base()
            ->groupBy('supplier_id')
            ->selectRaw(<<<'SQL'
                supplier_id,
                COUNT(*) AS orders_placed,
                SUM(total) AS total_value,
                SUM(status = 'received') AS delivered,
                SUM(CASE WHEN status = 'received' THEN total ELSE 0 END) AS received_value,
                SUM(status = 'received' AND received_date IS NOT NULL AND expected_delivery_date IS NOT NULL) AS dated,
                SUM(status = 'received' AND received_date <= expected_delivery_date) AS on_time,
                AVG(CASE WHEN status = 'received' THEN DATEDIFF(received_date, order_date) END) AS avg_actual_days,
                AVG(CASE WHEN status = 'received' THEN DATEDIFF(expected_delivery_date, order_date) END) AS avg_quoted_days
            SQL)
            ->get();

        // Received orders that needed more than one delivery (a 'partial' entry in the history).
        $partial = $base()
            ->where('status', PurchaseOrder::STATUS_RECEIVED)
            ->whereHas('approvals', fn ($q) => $q->where('action', 'partial'))
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, COUNT(*) AS c')
            ->pluck('c', 'supplier_id');

        $suppliers = Supplier::withTrashed()->whereIn('id', $stats->pluck('supplier_id'))->get()->keyBy('id');

        return $stats->map(function ($row) use ($suppliers, $partial) {
            $supplier = $suppliers[$row->supplier_id] ?? null;
            $delivered = (int) $row->delivered;
            $dated = (int) $row->dated;

            $avgActual = $row->avg_actual_days !== null ? round((float) $row->avg_actual_days, 1) : null;
            $avgQuoted = $row->avg_quoted_days !== null ? round((float) $row->avg_quoted_days, 1) : null;
            $onTimePct = $dated > 0 ? round($row->on_time / $dated * 100, 1) : null;
            $accuracy = $delivered > 0
                ? round(($delivered - (int) ($partial[$row->supplier_id] ?? 0)) / $delivered * 100, 1)
                : null;

            return [
                'supplier_id' => $row->supplier_id,
                'name' => $supplier?->name ?? 'Unknown',
                'deleted' => (bool) $supplier?->trashed(),
                'orders_placed' => (int) $row->orders_placed,
                'delivered' => $delivered,
                'dated' => $dated,
                'on_time' => (int) $row->on_time,
                'on_time_pct' => $onTimePct,
                'avg_actual_days' => $avgActual,
                'avg_quoted_days' => $avgQuoted,
                'variance_days' => ($avgActual !== null && $avgQuoted !== null) ? round($avgActual - $avgQuoted, 1) : null,
                'accuracy_pct' => $accuracy,
                'total_value' => round((float) $row->total_value, 2),
                'received_value' => round((float) $row->received_value, 2),
                'rating' => $this->rating($onTimePct, $accuracy),
            ];
        })->sortBy('name')->values();
    }

    /** Best three and the three that need attention (suppliers with at least one delivery). */
    public function rankings(Collection $rows): array
    {
        $ranked = $rows
            ->filter(fn ($r) => $r['rating']['score'] !== null)
            ->sort(fn ($a, $b) => [$b['rating']['score'], $b['delivered']] <=> [$a['rating']['score'], $a['delivered']])
            ->values();

        $top = $ranked->take(3)->values();
        $bottom = $ranked->reverse()->take(3)
            ->reject(fn ($r) => $top->contains('supplier_id', $r['supplier_id']))   // never list a supplier twice
            ->values();

        return ['top' => $top, 'bottom' => $bottom];
    }

    /** Deliveries per month for the last N months: on time vs late. */
    public function deliveryTrend(int $months = 6, ?int $supplierId = null): Collection
    {
        $since = now()->subMonths($months - 1)->startOfMonth();

        $rows = PurchaseOrder::query()
            ->where('status', PurchaseOrder::STATUS_RECEIVED)
            ->whereNotNull('received_date')
            ->whereNotNull('expected_delivery_date')
            ->where('received_date', '>=', $since)
            ->when($supplierId, fn ($q, $v) => $q->where('supplier_id', $v))
            ->groupBy('month')
            ->orderBy('month')
            ->selectRaw("DATE_FORMAT(received_date, '%Y-%m') AS month, COUNT(*) AS delivered, SUM(received_date <= expected_delivery_date) AS on_time, AVG(DATEDIFF(received_date, expected_delivery_date)) AS avg_delay")
            ->get()
            ->keyBy('month');

        return collect(range(0, $months - 1))->map(function ($i) use ($since, $rows) {
            $month = $since->copy()->addMonths($i);
            $row = $rows[$month->format('Y-m')] ?? null;
            $delivered = (int) ($row->delivered ?? 0);
            $onTime = (int) ($row->on_time ?? 0);

            return [
                'label' => $month->format('M Y'),
                'on_time' => $onTime,
                'late' => $delivered - $onTime,
                'avg_delay' => $row ? round((float) $row->avg_delay, 1) : null,
            ];
        });
    }

    /** Average of on-time % and accuracy %, turned into a label. */
    private function rating(?float $onTime, ?float $accuracy): array
    {
        $scores = array_filter([$onTime, $accuracy], fn ($s) => $s !== null);

        if ($scores === []) {
            return ['label' => 'No deliveries yet', 'color' => 'secondary', 'score' => null];
        }

        $score = round(array_sum($scores) / count($scores), 1);

        return match (true) {
            $score >= 90 => ['label' => 'Excellent', 'color' => 'success', 'score' => $score],
            $score >= 75 => ['label' => 'Good', 'color' => 'primary', 'score' => $score],
            $score >= 50 => ['label' => 'Fair', 'color' => 'warning', 'score' => $score],
            default => ['label' => 'Poor', 'color' => 'danger', 'score' => $score],
        };
    }
}