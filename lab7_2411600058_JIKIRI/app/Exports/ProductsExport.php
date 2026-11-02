<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;

class ProductsExport extends Export
{
    public function __construct(private Builder $query) {}

    public function title(): string
    {
        return 'Products';
    }

    public function headings(): array
    {
        return ['SKU', 'Product', 'Category', 'Supplier', 'Quantity', 'Reorder Level', 'Unit Price', 'Stock Value', 'Status'];
    }

    public function rows(): iterable
    {
        foreach ((clone $this->query)->with('suppliers')->orderBy('name')->lazy(200) as $p) {
            yield [
                $p->sku,
                $p->name,
                $p->category,
                $p->preferredSupplier()?->name ?? $p->supplier ?? '',
                $p->quantity,
                $p->reorder_level,
                number_format($p->unit_price, 2, '.', ''),
                number_format($p->quantity * $p->unit_price, 2, '.', ''),
                $p->stock_status_label,
            ];
        }
    }

    public function summary(): array
    {
        $value = (float) (clone $this->query)->selectRaw('COALESCE(SUM(quantity * unit_price), 0) AS v')->value('v');

        return [
            'Products' => (clone $this->query)->count(),
            'Units in stock' => (int) (clone $this->query)->sum('quantity'),
            'Stock value' => '$' . number_format($value, 2),
        ];
    }
}