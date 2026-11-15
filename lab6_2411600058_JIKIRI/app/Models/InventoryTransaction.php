<?php

namespace App\Models;

use App\Observers\InventoryTransactionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;

#[ObservedBy(InventoryTransactionObserver::class)]
class InventoryTransaction extends Model
{
    public const TYPE_IN = 'stock_in';
    public const TYPE_OUT = 'stock_out';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPES = [self::TYPE_IN, self::TYPE_OUT, self::TYPE_ADJUSTMENT];

    protected $fillable = [
        'product_id', 'user_id', 'type', 'quantity', 'balance_after', 'reference', 'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'balance_after' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeStockIn(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_IN);
    }

    public function scopeStockOut(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_OUT);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest()->latest('id'); // id breaks ties inside the same second
    }

    /** Signed effect on stock: in = +, out = -, adjustment is already signed. */
    public function delta(): int
    {
        return match ($this->type) {
            self::TYPE_IN => abs($this->quantity),
            self::TYPE_OUT => -abs($this->quantity),
            default => $this->quantity,
        };
    }
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_IN => 'Stock In',
            self::TYPE_OUT => 'Stock Out',
            default => 'Adjustment',
        };
    }

    public function getTypeColorAttribute(): string
    {
        return 'movement';
    }

    /**
     * The only way to move stock. Insert + stock update succeed or fail together.
     *
     * @throws \App\Exceptions\InsufficientStockException
     */
    public static function record(
        Product|int $product,
        string $type,
        int $quantity,
        ?string $reference = null,
        ?string $notes = null,
        ?int $userId = null,
    ): self {
        return DB::transaction(fn () => self::create([
            'product_id' => $product instanceof Product ? $product->id : $product,
            'user_id' => $userId ?? auth()->id(),
            'type' => $type,
            'quantity' => $quantity,
            'reference' => $reference,
            'notes' => $notes,
        ]));
    }
}