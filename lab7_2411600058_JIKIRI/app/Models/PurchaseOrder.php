<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\ProcurementWidgets;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SENT = 'sent';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING => 'Pending Approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_SENT => 'Sent to Supplier',
        self::STATUS_RECEIVED => 'Received',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'reference_number', 'supplier_id', 'status', 'order_date', 'expected_delivery_date',
        'received_date', 'subtotal', 'tax_rate', 'tax', 'total', 'notes', 'terms',
        'created_by', 'approved_by', 'approved_at', 'rejection_reason', 'sent_at', 'auto_generated',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'received_date' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'auto_generated' => 'boolean',
    ];

    // ---------- Relationships ----------

    /** withTrashed(): an order must still show its supplier after the supplier is soft-deleted. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseOrderApproval::class)->oldest();
    }

    /** Inventory transactions created when this order was received (polymorphic). */
    public function inventoryTransactions(): MorphMany
    {
        return $this->morphMany(InventoryTransaction::class, 'source');
    }

    // ---------- Query scopes ----------

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /** Orders not yet finished (not received, cancelled or rejected). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_RECEIVED, self::STATUS_CANCELLED, self::STATUS_REJECTED]);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // ---------- Business helpers ----------

    /** Recalculates subtotal, tax and total from the line items. */
    public function calculateTotal(): void
    {
        $subtotal = (float) $this->items()->get()->sum(fn ($item) => $item->quantity * $item->unit_price);
        $tax = round($subtotal * ((float) $this->tax_rate / 100), 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
        ])->save();
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
    /** 2 when the total reaches the approval threshold, otherwise 1. */
    public function requiredApprovalLevels(): int
    {
        return (float) $this->total >= (float) config('procurement.approval_threshold', 1000) ? 2 : 1;
    }

    /** Highest approval level recorded so far (0 = none). */
    public function approvedLevel(): int
    {
        return (int) $this->approvals()->reorder()->where('action', 'approved')->max('level');
    }

    /** Pending, high-value order that already has its first-level approval. */
    public function isAwaitingFinalApproval(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->requiredApprovalLevels() > 1
            && $this->approvedLevel() >= 1;
    }

    public function canBeReceived(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_SENT], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED], true)
            && ! $this->items()->where('received_quantity', '>', 0)->exists();
    }
    /** Some goods have arrived, but the order is not complete. */
    public function isPartiallyReceived(): bool
    {
        return ! in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_CANCELLED], true)
            && $this->items()->where('received_quantity', '>', 0)->exists();
    }

    /** Next reference for today: PO-20261030-0001, PO-20261030-0002, ... */
    public static function generateReferenceNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ymd') . '-';

        $last = static::withTrashed()
            ->where('reference_number', 'like', $prefix . '%')
            ->orderByDesc('reference_number')
            ->value('reference_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    // ---------- Accessors for badges ----------

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
    /** The user created the order, or submitted it for approval. */
    public function isOwnedBy(User $user): bool
    {
        return $this->created_by === $user->id
            || $this->approvals()->reorder()->where('action', 'submitted')->where('user_id', $user->id)->exists();
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'secondary',
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'info',
            self::STATUS_SENT => 'primary',
            self::STATUS_RECEIVED => 'success',
            self::STATUS_REJECTED => 'danger',
            default => 'dark',
        };
    }
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('reference_number', 'like', "%{$term}%")
            ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$term}%")));
    }

    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeOrderedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q, $v) => $q->whereDate('order_date', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('order_date', '<=', $v));
    }
        protected static function booted(): void
    {
        $flush = fn () => DB::afterCommit(fn () => ProcurementWidgets::flush());

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }
}