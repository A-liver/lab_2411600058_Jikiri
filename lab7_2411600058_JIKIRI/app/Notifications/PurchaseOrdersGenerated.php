<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class PurchaseOrdersGenerated extends Notification
{
    use Queueable;

    public function __construct(public Collection $orders) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** Shown in the notification bell. */
    public function toArray(object $notifiable): array
    {
        $count = $this->orders->count();
        $items = $this->orders->sum(fn ($order) => $order->items()->count());

        return [
            'message' => "Reorder check created {$count} draft purchase order(s) covering {$items} product(s). Review and submit them.",
            'url' => route('purchase-orders.index', ['status' => 'draft'], false),   // relative link
            'order_ids' => $this->orders->pluck('id')->all(),
        ];
    }
}