<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\ProcurementWidgets;
use Illuminate\Support\Facades\DB;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'product_id', 'quantity', 'unit_price', 'received_quantity'];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'received_quantity' => 'integer',
    ];
    
    protected static function booted(): void
    {
        $flush = fn () => DB::afterCommit(fn () => ProcurementWidgets::flush());

        static::saved($flush);
        static::deleted($flush);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getLineTotalAttribute(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }

    public function getOutstandingQuantityAttribute(): int
    {
        return max($this->quantity - $this->received_quantity, 0);
    }
}