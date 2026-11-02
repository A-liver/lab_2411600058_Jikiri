<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;

class PurchaseOrdersExport extends Export
{
    public function __construct(private Builder $query) {}

    public function title(): string
    {
        return 'Purchase Orders';
    }

    public function headings(): array
    {
        return ['Reference', 'Supplier', 'Status', 'Origin', 'Order Date', 'Expected', 'Received', 'Subtotal', 'Tax', 'Total', 'Created By'];
    }

    public function rows(): iterable
    {
        foreach ((clone $this->query)->with(['supplier', 'createdBy'])->latest('id')->lazy(200) as $o) {
            yield [
                $o->reference_number,
                $o->supplier->name,
                $o->status_label,
                $o->auto_generated ? 'Auto' : 'Manual',
                $o->order_date->format('Y-m-d'),
                $o->expected_delivery_date?->format('Y-m-d') ?? '',
                $o->received_date?->format('Y-m-d') ?? '',
                number_format($o->subtotal, 2, '.', ''),
                number_format($o->tax, 2, '.', ''),
                number_format($o->total, 2, '.', ''),
                $o->createdBy->name ?? 'System',
            ];
        }
    }

    public function summary(): array
    {
        return [
            'Orders' => (clone $this->query)->count(),
            'Total value' => '$' . number_format((float) (clone $this->query)->sum('total'), 2),
        ];
    }
}