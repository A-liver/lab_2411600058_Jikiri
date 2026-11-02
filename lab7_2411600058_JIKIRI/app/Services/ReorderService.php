<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\PurchaseOrdersGenerated;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Mail\LowStockAlert;
use Illuminate\Support\Facades\Mail;

class ReorderService
{
    public const USAGE_WINDOW_DAYS = 30;        // how far back usage is measured
    public const COVER_DAYS = 14;               // order enough for this many days of demand
    public const DEFAULT_LEAD_TIME_DAYS = 7;    // used when a product has no supplier yet

    /** Orders confirmed to be on the way. Drafts are not, because nobody has submitted them yet. */
    private const COMMITTED_STATUSES = [
        PurchaseOrder::STATUS_PENDING,
        PurchaseOrder::STATUS_APPROVED,
        PurchaseOrder::STATUS_SENT,
    ];

    /** product_id => units sold in the usage window (loaded once per run). */
    private ?Collection $usage = null;

    // ---------- calculations ----------

    /** Average units sold per day over the last 30 days (days without sales count as zero). */
    public function averageDailyUsage(Product $product): float
    {
        $this->usage ??= InventoryTransaction::query()
            ->where('type', InventoryTransaction::TYPE_OUT)
            ->where('created_at', '>=', now()->subDays(self::USAGE_WINDOW_DAYS))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->pluck('total', 'product_id');

        return (float) ($this->usage[$product->id] ?? 0) / self::USAGE_WINDOW_DAYS;
    }

    /** (average daily usage × lead time) + safety stock, never below the product's reorder level. */
    public function calculateReorderPoint(Product $product): int
    {
        $leadTime = $this->supplierFor($product)?->lead_time_days ?? self::DEFAULT_LEAD_TIME_DAYS;

        $calculated = (int) ceil($this->averageDailyUsage($product) * $leadTime) + (int) $product->safety_stock;

        return max($calculated, (int) $product->reorder_level);
    }

    /**
     * Simplified EOQ: refill up to (reorder point + two weeks of demand), then respect
     * the supplier's minimum order quantity and pack size. $onOrder = units already ordered.
     */
    public function calculateOptimalOrderQuantity(Product $product, int $onOrder = 0): int
    {
        $reorderPoint = $this->calculateReorderPoint($product);
        $demandCover = (int) ceil($this->averageDailyUsage($product) * self::COVER_DAYS);
        $targetLevel = $reorderPoint + max($demandCover, (int) $product->reorder_level);

        $needed = $targetLevel - ($product->quantity + $onOrder);

        if ($needed <= 0) {
            return 0;
        }

        $pivot = $this->supplierFor($product)?->pivot;
        $minimum = max((int) ($pivot->min_order_quantity ?? 1), 1);
        $packSize = max((int) ($pivot->pack_size ?? 1), 1);

        $quantity = max($needed, $minimum);

        return (int) (ceil($quantity / $packSize) * $packSize);
    }

    /** The active supplier to buy from: the preferred one, otherwise the cheapest. */
    public function supplierFor(Product $product): ?Supplier
    {
        $active = $product->suppliers->where('is_active', true);

        return $active->firstWhere('pivot.is_preferred', true)
            ?? $active->sortBy('pivot.unit_cost')->first();
    }

    // ---------- detection ----------

    /**
     * Products whose stock position (on hand + already on order) is at or below the reorder point.
     * Each row: product, on_order, reorder_point, quantity (suggested), supplier (null if none).
     */
    public function getProductsNeedingReorder(): Collection
    {
        $onOrder = $this->openQuantities(self::COMMITTED_STATUSES);
        $inDraft = $this->openQuantities([PurchaseOrder::STATUS_DRAFT]);

        return Product::with('suppliers')->orderBy('name')->get()
            ->map(function (Product $product) use ($onOrder, $inDraft) {
                $ordered = (int) ($onOrder[$product->id] ?? 0);
                $reorderPoint = $this->calculateReorderPoint($product);

                return [
                    'product' => $product,
                    'on_order' => $ordered,
                    'in_draft' => (int) ($inDraft[$product->id] ?? 0),   // already prepared, waiting to be submitted
                    'reorder_point' => $reorderPoint,
                    'needs_reorder' => ($product->quantity + $ordered) <= $reorderPoint,
                    'quantity' => $this->calculateOptimalOrderQuantity($product, $ordered),
                    'supplier' => $this->supplierFor($product),
                ];
            })
            ->filter(fn ($row) => $row['needs_reorder'] && $row['quantity'] > 0)
            ->values();
    }

    /** Groups reorder rows by supplier id. */
    public function groupProductsBySupplier(Collection $rows): Collection
    {
        return $rows->groupBy(fn ($row) => $row['supplier']->id);
    }

    // ---------- generation ----------

    /**
     * Creates one DRAFT purchase order per supplier for everything that needs reordering,
     * and notifies admins and managers. Returns ['orders', 'skipped', 'rows'].
     */
    public function generatePurchaseOrders(): array
    {
        $rows = $this->getProductsNeedingReorder();

        $fresh = $rows->filter(fn ($row) => $row['in_draft'] === 0);              // not covered by a draft yet
        $drafted = $rows->filter(fn ($row) => $row['in_draft'] > 0)->values();    // a draft is already waiting

        $orderable = $fresh->filter(fn ($row) => $row['supplier'] !== null);
        $skipped = $fresh->filter(fn ($row) => $row['supplier'] === null)->values();   // no active supplier

        $orders = collect();

        foreach ($this->groupProductsBySupplier($orderable) as $group) {
            $orders->push($this->createDraftOrder($group->first()['supplier'], $group));
        }

        if ($orders->isNotEmpty()) {
            Notification::send(User::alertRecipients()->get(), new PurchaseOrdersGenerated($orders));
        }

        // Email digest only for products that are new news (not ones already sitting in a draft).
        if ($fresh->isNotEmpty()) {
            $items = $fresh->map(fn ($row) => [
                'name' => $row['product']->name,
                'sku' => $row['product']->sku,
                'stock' => $row['product']->quantity,
                'reorder_point' => $row['reorder_point'],
                'quantity' => $row['quantity'],
                'supplier' => $row['supplier']?->name ?? 'No active supplier',
            ])->values()->all();

            $references = $orders->pluck('reference_number')->all();

            User::alertRecipients()->get()->each(
                fn (User $recipient) => Mail::to($recipient)->queue(new LowStockAlert($items, $references))
            );
        }

        return ['orders' => $orders, 'skipped' => $skipped, 'drafted' => $drafted, 'rows' => $rows];
    }

    private function createDraftOrder(Supplier $supplier, Collection $group): PurchaseOrder
    {
        return DB::transaction(function () use ($supplier, $group) {
            $order = PurchaseOrder::create([
                'reference_number' => PurchaseOrder::generateReferenceNumber(),
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'order_date' => today(),
                'expected_delivery_date' => today()->addDays($supplier->lead_time_days),
                'tax_rate' => 0,
                'terms' => 'Payment terms: ' . $supplier->payment_terms,
                'notes' => 'Auto-generated by the reorder check on ' . now()->format('Y-m-d H:i') . '.',
                'created_by' => null,                       // created by the system
                'auto_generated' => true,
            ]);

            $order->items()->createMany($group->map(fn ($row) => [
                'product_id' => $row['product']->id,
                'quantity' => $row['quantity'],
                'unit_price' => (float) ($row['supplier']->pivot->unit_cost ?? 0),
            ])->values()->all());

            $order->calculateTotal();

            if ((float) $order->subtotal < (float) $supplier->minimum_order_amount) {
                $order->update(['notes' => $order->notes . ' Below the supplier minimum order of $'
                    . number_format($supplier->minimum_order_amount, 2) . ': add items before submitting.']);
            }

            $order->approvals()->create([
                'user_id' => null,
                'action' => 'generated',
                'comment' => $group->count() . ' item(s) at or below the reorder point.',
            ]);

            return $order;
        });
    }

    /** product_id => units still expected on orders with one of the given statuses. */
    private function openQuantities(array $statuses): Collection
    {
        return PurchaseOrderItem::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->whereNull('purchase_orders.deleted_at')
            ->whereIn('purchase_orders.status', $statuses)
            ->groupBy('purchase_order_items.product_id')
            ->selectRaw('purchase_order_items.product_id, SUM(GREATEST(purchase_order_items.quantity - purchase_order_items.received_quantity, 0)) AS qty')
            ->pluck('qty', 'product_id');
    }
}