<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;

class SearchService
{
    /** Name, SKU or supplier text, category, stock status, supplier, price range. */
    public function products(array $f): Builder
    {
        return Product::query()
            ->when($this->value($f, 'search'), fn ($q, $v) => $q->search($v))
            ->when($this->value($f, 'category'), fn ($q, $v) => $q->inCategory($v))
            ->when($this->value($f, 'stock_status'), fn ($q, $v) => $q->stockStatus($v))
            ->when($this->value($f, 'supplier_id'), fn ($q, $v) => $q->suppliedBy((int) $v))
            ->when($this->value($f, 'min_price'), fn ($q, $v) => $q->where('unit_price', '>=', (float) $v))
            ->when($this->value($f, 'max_price'), fn ($q, $v) => $q->where('unit_price', '<=', (float) $v));
    }

    /** Reference or supplier name, supplier, status, date range, auto/manual, total range. */
    public function purchaseOrders(array $f): Builder
    {
        return PurchaseOrder::query()
            ->when($this->value($f, 'search'), fn ($q, $v) => $q->search($v))
            ->when($this->value($f, 'supplier_id'), fn ($q, $v) => $q->forSupplier((int) $v))
            ->when($this->value($f, 'status'), fn ($q, $v) => $q->byStatus($v))
            ->when($this->value($f, 'from') || $this->value($f, 'to'),
                fn ($q) => $q->orderedBetween($this->value($f, 'from'), $this->value($f, 'to')))
            ->when(isset($f['auto']) && in_array((string) $f['auto'], ['0', '1'], true),
                fn ($q) => $q->where('auto_generated', (bool) (int) $f['auto']))
            ->when($this->value($f, 'min_total'), fn ($q, $v) => $q->where('total', '>=', (float) $v))
            ->when($this->value($f, 'max_total'), fn ($q, $v) => $q->where('total', '<=', (float) $v));
    }

    /** Product, type, user, date range, reference number. */
    public function transactions(array $f): Builder
    {
        return InventoryTransaction::query()
            ->when($this->value($f, 'product_id'), fn ($q, $v) => $q->where('product_id', (int) $v))
            ->when($this->value($f, 'type'), fn ($q, $v) => $q->where('type', $v))
            ->when($this->value($f, 'user_id'), fn ($q, $v) => $q->byUser((int) $v))
            ->when($this->value($f, 'from') || $this->value($f, 'to'),
                fn ($q) => $q->between($this->value($f, 'from'), $this->value($f, 'to')))
            ->when($this->value($f, 'search'), fn ($q, $v) => $q->where('reference_number', 'like', "%{$v}%"));
    }

    /** Empty strings and the "all" option mean "no filter". */
    private function value(array $filters, string $key): mixed
    {
        $value = $filters[$key] ?? null;

        return ($value === null || $value === '' || $value === 'all') ? null : $value;
    }
}