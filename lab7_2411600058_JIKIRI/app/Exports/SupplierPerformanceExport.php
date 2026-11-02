<?php

namespace App\Exports;

use Illuminate\Support\Collection;

class SupplierPerformanceExport extends Export
{
    /** @param Collection $data rows from SupplierPerformanceService::summary() */
    public function __construct(private Collection $data) {}

    public function title(): string
    {
        return 'Supplier Performance';
    }

    public function headings(): array
    {
        return ['Supplier', 'Orders Placed', 'Delivered', 'On-Time %', 'Avg Actual Days', 'Avg Quoted Days', 'Variance Days', 'Accuracy %', 'Total Value', 'Received Value', 'Rating'];
    }

    public function rows(): iterable
    {
        foreach ($this->data as $r) {
            yield [
                $r['name'] . ($r['deleted'] ? ' (deleted)' : ''),
                $r['orders_placed'],
                $r['delivered'],
                $r['on_time_pct'] ?? '',
                $r['avg_actual_days'] ?? '',
                $r['avg_quoted_days'] ?? '',
                $r['variance_days'] ?? '',
                $r['accuracy_pct'] ?? '',
                number_format($r['total_value'], 2, '.', ''),
                number_format($r['received_value'], 2, '.', ''),
                $r['rating']['label'],
            ];
        }
    }

    public function summary(): array
    {
        $dated = $this->data->sum('dated');

        return [
            'Suppliers' => $this->data->count(),
            'Orders placed' => $this->data->sum('orders_placed'),
            'Overall on-time' => $dated > 0 ? round($this->data->sum('on_time') / $dated * 100, 1) . '%' : 'n/a',
            'Total value' => '$' . number_format($this->data->sum('total_value'), 2),
        ];
    }
}