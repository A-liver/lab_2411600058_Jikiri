<?php

namespace App\Exports;

use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;

class TransactionsExport extends Export
{
    public function __construct(private Builder $query) {}

    public function title(): string
    {
        return 'Inventory Transactions';
    }

    public function headings(): array
    {
        return ['Date / Time', 'Product', 'SKU', 'Type', 'Quantity', 'Balance After', 'Reference', 'Source', 'User', 'Notes'];
    }

    public function rows(): iterable
    {
        foreach ((clone $this->query)->with(['product', 'user', 'source'])->latest('id')->lazy(200) as $t) {
            yield [
                $t->created_at->format('Y-m-d H:i'),
                $t->product->name,
                $t->product->sku,
                $t->type_label,
                $t->signed_quantity,                                   // negative for stock out
                $t->balance_after ?? '',
                $t->reference_number ?? '',
                $t->source instanceof PurchaseOrder ? $t->source->reference_number : '',
                $t->user->name ?? 'System',
                $t->notes ?? '',
            ];
        }
    }

    public function summary(): array
    {
        return [
            'Transactions' => (clone $this->query)->count(),
            'Total stock in' => (int) (clone $this->query)->where('type', InventoryTransaction::TYPE_IN)->sum('quantity'),
            'Total stock out' => (int) (clone $this->query)->where('type', InventoryTransaction::TYPE_OUT)->sum('quantity'),
            'Net adjustments' => (int) (clone $this->query)->where('type', InventoryTransaction::TYPE_ADJUSTMENT)->sum('quantity'),
        ];
    }
}