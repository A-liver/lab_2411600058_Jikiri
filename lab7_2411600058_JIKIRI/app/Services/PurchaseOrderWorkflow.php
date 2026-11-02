<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryTransaction;
use App\Mail\PurchaseOrderApproved;
use App\Mail\PurchaseOrderCreated;
use App\Mail\PurchaseOrderSent;
use Illuminate\Support\Facades\Mail;
class PurchaseOrderWorkflow
{
    /** Draft → Pending, then email the people who can approve it. */
    public function submit(PurchaseOrder $order, User $user): void
    {
        DB::transaction(function () use ($order, $user) {
            $locked = $this->lock($order);

            if ($locked->status !== PurchaseOrder::STATUS_DRAFT) {
                throw new DomainException('Only draft orders can be submitted.');
            }
            if ($locked->items()->count() === 0) {
                throw new DomainException('Add at least one line item before submitting.');
            }

            $supplier = $locked->supplier;
            if ($supplier->trashed() || ! $supplier->is_active) {
                throw new DomainException("{$supplier->name} is no longer an active supplier.");
            }
            if ((float) $locked->subtotal < (float) $supplier->minimum_order_amount) {
                throw new DomainException('The subtotal is below this supplier\'s minimum order of $'
                    . number_format($supplier->minimum_order_amount, 2) . '.');
            }

            $locked->update(['status' => PurchaseOrder::STATUS_PENDING]);
            $this->log($locked, $user, 'submitted');
        });

        // After the commit: the emails are queued, so the page does not wait for them.
        User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_MANAGER])
            ->where('id', '!=', $user->id)
            ->each(fn (User $approver) => Mail::to($approver)
                ->queue(new PurchaseOrderCreated($order->fresh(['supplier', 'items.product']))));
    }

    /**
     * Approve. Returns true when the order is fully approved,
     * false when only the first level was recorded (waiting for an admin).
     */
    public function approve(PurchaseOrder $order, User $user, ?string $comment = null): bool
    {
        $final = DB::transaction(function () use ($order, $user, $comment) {
            $locked = $this->lock($order);

            if (! $locked->canBeApproved()) {
                throw new DomainException('This purchase order is no longer waiting for approval.');
            }

            $required = $locked->requiredApprovalLevels();
            $level = $user->isAdmin() ? $required : 1;      // an admin approval is always final

            $this->log($locked, $user, 'approved', $level, $comment);

            if ($level < $required) {
                return false;
            }

            $locked->update([
                'status' => PurchaseOrder::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            return true;
        });

        if ($final) {
            // Tell whoever created or submitted the order (except the approver).
            $ids = $order->approvals()->reorder()->where('action', 'submitted')->pluck('user_id')
                ->push($order->created_by)
                ->filter()
                ->unique()
                ->reject(fn ($id) => $id === $user->id);

            User::whereIn('id', $ids)->each(fn (User $owner) => Mail::to($owner)
                ->queue(new PurchaseOrderApproved($order->fresh(['supplier', 'items.product']), $user)));
        }

        return $final;
    }

    public function reject(PurchaseOrder $order, User $user, string $reason): void
    {
        DB::transaction(function () use ($order, $user, $reason) {
            $locked = $this->lock($order);

            if (! $locked->canBeApproved()) {
                throw new DomainException('This purchase order is no longer waiting for approval.');
            }

            $locked->update([
                'status' => PurchaseOrder::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
            ]);
            $this->log($locked, $user, 'rejected', $user->isAdmin() ? 2 : 1, $reason);
        });
    }

    /** Rejected → Draft, so the creator can fix and resubmit. */
    public function reopen(PurchaseOrder $order, User $user): void
    {
        DB::transaction(function () use ($order, $user) {
            $locked = $this->lock($order);

            if ($locked->status !== PurchaseOrder::STATUS_REJECTED) {
                throw new DomainException('Only rejected orders can be reopened.');
            }

            $locked->update(['status' => PurchaseOrder::STATUS_DRAFT, 'rejection_reason' => null]);
            $this->log($locked, $user, 'reopened');
        });
    }

    /** Approved → Sent. The email to the supplier is added in step 7. */
    public function send(PurchaseOrder $order, User $user): bool
    {
        DB::transaction(function () use ($order, $user) {
            $locked = $this->lock($order);

            if ($locked->status !== PurchaseOrder::STATUS_APPROVED) {
                throw new DomainException('Only approved orders can be sent to the supplier.');
            }

            $locked->update(['status' => PurchaseOrder::STATUS_SENT, 'sent_at' => now()]);
            $this->log($locked, $user, 'sent');
        });

        $supplier = $order->supplier;

        if (! $supplier->email) {
            return false;                                   // no email on file: marked as sent, nothing to mail
        }

        Mail::to($supplier->email)->queue(new PurchaseOrderSent($order->fresh(['supplier', 'items.product'])));

        return true;
    }

    public function cancel(PurchaseOrder $order, User $user): void
    {
        DB::transaction(function () use ($order, $user) {
            $locked = $this->lock($order);

            if (! $locked->canBeCancelled()) {
                throw new DomainException('This purchase order can no longer be cancelled.');
            }

            $locked->update(['status' => PurchaseOrder::STATUS_CANCELLED]);
            $this->log($locked, $user, 'cancelled');
        });
    }
    /**
 * Record a (partial) delivery. $quantities is [purchase_order_item_id => quantity received now].
 * Returns true when the whole order has now been received.
 */
    public function receive(PurchaseOrder $order, User $user, array $quantities, ?string $deliveryNote = null, ?string $notes = null): bool
    {
        return DB::transaction(function () use ($order, $user, $quantities, $deliveryNote, $notes) {
            $locked = $this->lock($order);

            if (! $locked->canBeReceived()) {
                throw new DomainException('This purchase order is not ready to receive goods.');
            }

            // Work out what is being received and refuse over-receiving.
            $lines = [];
            foreach ($locked->items()->lockForUpdate()->get() as $item) {
                $qty = (int) ($quantities[$item->id] ?? 0);

                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $item->outstanding_quantity) {
                    throw new DomainException("Cannot receive {$qty} of \"{$item->product->name}\": only {$item->outstanding_quantity} still outstanding.");
                }

                $lines[] = [$item, $qty];
            }

            if ($lines === []) {
                throw new DomainException('Enter a received quantity for at least one item.');
            }

            $parts = ["Received against {$locked->reference_number}"];
            if ($deliveryNote) {
                $parts[] = "Delivery note: {$deliveryNote}";
            }
            if ($notes) {
                $parts[] = $notes;
            }
            $note = implode('. ', $parts);

            foreach ($lines as [$item, $qty]) {
                // Lab 6 ledger: creates the stock-in, the observer updates the product quantity.
                InventoryTransaction::record([
                    'product_id' => $item->product_id,
                    'user_id' => $user->id,
                    'type' => InventoryTransaction::TYPE_IN,
                    'quantity' => $qty,
                    'reference_number' => $locked->reference_number,
                    'notes' => $note,
                    'source_type' => $locked->getMorphClass(),     // polymorphic link back to this order
                    'source_id' => $locked->id,
                ]);

                $item->increment('received_quantity', $qty);
            }

            $complete = $locked->items()->get()->every(fn ($i) => $i->received_quantity >= $i->quantity);

            if ($complete) {
                $locked->update(['status' => PurchaseOrder::STATUS_RECEIVED, 'received_date' => now()->toDateString()]);
                $this->log($locked, $user, 'received', 1, $note);
            } else {
                $this->log($locked, $user, 'partial', 1, $note);
            }

            return $complete;
        });
    }

    // ---------- helpers ----------

    private function lock(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::lockForUpdate()->findOrFail($order->id);
    }

    private function log(PurchaseOrder $order, User $user, string $action, int $level = 1, ?string $comment = null): void
    {
        $order->approvals()->create([
            'user_id' => $user->id,
            'action' => $action,
            'level' => $level,
            'comment' => $comment,
        ]);
    }
}