<?php

namespace App\Observers;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Notification;

class InventoryTransactionObserver
{
    /** Runs BEFORE the insert, so a bad movement never reaches the table. */
    public function creating(InventoryTransaction $tx): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Use InventoryTransaction::record() so the movement runs inside a DB transaction.');
        }

        $isAdjustment = $tx->type === InventoryTransaction::TYPE_ADJUSTMENT;

        if ($tx->quantity === 0 || (! $isAdjustment && $tx->quantity < 0)) {
            throw new InvalidArgumentException('Invalid stock movement quantity.');
        }

        // lock the row so two simultaneous requests cannot both pass the check
        $product = Product::whereKey($tx->product_id)->lockForUpdate()->firstOrFail();

        $newQuantity = $product->quantity + $tx->delta();

        if ($newQuantity < 0) {
            throw new InsufficientStockException($product, abs($tx->delta()));
        }

        $tx->balance_after = $newQuantity; // feeds the running-balance column later
    }

    /** Runs AFTER the insert, still inside the same transaction. */
    public function created(InventoryTransaction $tx): void
    {
        Product::whereKey($tx->product_id)->update(['quantity' => $tx->balance_after]);

        $product = Product::find($tx->product_id);
        $previous = $tx->balance_after - $tx->delta();

        // alert only on the move from "fine" to "at or below reorder level"
        if ($previous > $product->reorder_level && $product->quantity <= $product->reorder_level) {
            $recipients = User::where('role', User::ROLE_ADMIN)->get();
            $notification = new LowStockNotification($product);

            Notification::sendNow($recipients, $notification, ['database']);

            DB::afterCommit(function () use ($recipients, $notification): void {
                Notification::sendNow($recipients, $notification, ['mail']);
            });
        }
    }
}