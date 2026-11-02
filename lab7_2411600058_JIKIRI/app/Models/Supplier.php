<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\ProcurementWidgets;
use Illuminate\Support\Facades\DB;

class Supplier extends Model
{
    use SoftDeletes;

    public const PAYMENT_TERMS = ['COD', 'Net 15', 'Net 30', 'Net 45', 'Net 60'];

    protected $fillable = [
        'name', 'contact_person', 'email', 'phone', 'address',
        'payment_terms', 'lead_time_days', 'minimum_order_amount', 'is_active',
    ];

    protected $casts = [
        'lead_time_days' => 'integer',
        'minimum_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    
    protected static function booted(): void
    {
        $flush = fn () => DB::afterCommit(fn () => ProcurementWidgets::flush());

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    // ---------- Relationships ----------

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['supplier_sku', 'unit_cost', 'min_order_quantity', 'pack_size', 'is_preferred'])
            ->withTimestamps();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // ---------- Query scopes ----------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithLeadTime(Builder $query, int $days): Builder
    {
        return $query->where('lead_time_days', '<=', $days);
    }

    // ---------- Accessors ----------

    public function getFullContactAttribute(): string
    {
        return implode(' | ', array_filter([$this->contact_person, $this->email, $this->phone])) ?: 'No contact details';
    }

    /** On-time delivery percentage (null when nothing has been delivered yet). */
    public function getPerformanceRatingAttribute(): ?float
    {
        $delivered = $this->purchaseOrders()
            ->where('status', PurchaseOrder::STATUS_RECEIVED)
            ->whereNotNull('received_date')
            ->whereNotNull('expected_delivery_date');

        $total = (clone $delivered)->count();

        if ($total === 0) {
            return null;
        }

        $onTime = (clone $delivered)->whereColumn('received_date', '<=', 'expected_delivery_date')->count();

        return round($onTime / $total * 100, 1);
    }
}