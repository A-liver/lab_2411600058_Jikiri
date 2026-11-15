<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'category_id',
        'brand_id',
        'quantity',
        'reorder_level',
        'unit_price',
        'supplier',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reorder_level' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function latestTransaction(): HasOne
    {
        return $this->hasOne(InventoryTransaction::class)->latestOfMany();
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

    public function isLowStock(): bool
    {
        return $this->quantity > 0 && $this->quantity <= $this->reorder_level;
    }

    /** At or below the reorder level, zero included. Used for alerts. */
    public function needsReorder(): bool
    {
        return $this->quantity <= $this->reorder_level;
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->isOutOfStock()) {
            return 'out-of-stock';
        }

        return $this->isLowStock() ? 'low-stock' : 'in-stock';
    }

    public function getStockStatusLabelAttribute(): string
    {
        return match ($this->stock_status) {
            'out-of-stock' => 'Out of Stock',
            'low-stock' => 'Low Stock',
            default => 'In Stock',
        };
    }

    public function getStockStatusColorAttribute(): string
    {
        return match ($this->stock_status) {
            'out-of-stock' => 'danger',
            'low-stock' => 'warning',
            default => 'success',
        };
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'reorder_level');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('quantity', '<=', 0);
    }

    public function scopeInCategory(Builder $query, Category|int $category): Builder
    {
        return $query->where('category_id', $category instanceof Category ? $category->id : $category);
    }
    
    public function scopeAtReorderLevel(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'reorder_level');
    }
}
