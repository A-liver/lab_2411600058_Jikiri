<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    private function isBuyer(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function viewAny(User $user): bool
    {
        return $this->isBuyer($user) || $user->isWarehouse();
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->isBuyer($user);
    }

    /** Only draft orders, and only by their creator or an admin. */
    public function update(User $user, PurchaseOrder $order): bool
    {
        if ($order->status !== PurchaseOrder::STATUS_DRAFT) {
            return false;
        }

        // Auto-generated orders have no creator, so managers may work on them too.
        return $user->isAdmin()
            || $order->created_by === $user->id
            || ($order->created_by === null && $user->isManager());
    }

    public function submit(User $user, PurchaseOrder $order): bool
    {
        return $this->update($user, $order);
    }

    /** Lab rule: only draft orders, and only admins. */
    public function delete(User $user, PurchaseOrder $order): bool
    {
        return $user->isAdmin() && $order->status === PurchaseOrder::STATUS_DRAFT;
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $order->canBeCancelled() && $this->isBuyer($user);
    }

    /**
     * Managers and admins can approve, with two safeguards:
     *  - a manager cannot approve an order they created (admins are exempt)
     *  - once a manager has given the first-level approval, only an admin can finish it
     */
    public function approve(User $user, PurchaseOrder $order): bool
    {
        if (! $order->canBeApproved() || ! $this->isBuyer($user)) {
            return false;
        }

        if (! $user->isAdmin() && $order->isOwnedBy($user)) {
            return false;
        }

        if ($user->isManager() && $order->approvedLevel() >= 1) {
            return false;
        }

        return true;
    }

    public function reject(User $user, PurchaseOrder $order): bool
    {
        return $order->canBeApproved()
            && $this->isBuyer($user)
            && ($user->isAdmin() || ! $order->isOwnedBy($user));
    }

    public function send(User $user, PurchaseOrder $order): bool
    {
        return $order->status === PurchaseOrder::STATUS_APPROVED && $this->isBuyer($user);
    }

    /** Warehouse staff and admins record the goods received (built in step 5). */
    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $order->canBeReceived() && ($user->isAdmin() || $user->isWarehouse());
    }

    public function reopen(User $user, PurchaseOrder $order): bool
    {
        return $order->status === PurchaseOrder::STATUS_REJECTED
            && ($user->isAdmin() || $order->created_by === $user->id);
    }
}