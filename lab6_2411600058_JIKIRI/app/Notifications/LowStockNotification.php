<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $productId;
    public string $productName;
    public string $sku;
    public int $quantity;
    public int $reorderLevel;

    public function __construct(Product $product)
    {
        // snapshot the numbers instead of holding the model
        $this->productId = $product->id;
        $this->productName = $product->name;
        $this->sku = $product->sku;
        $this->quantity = $product->quantity;
        $this->reorderLevel = $product->reorder_level;

        // do not send until the stock transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Low stock: {$this->productName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->productName} (SKU {$this->sku}) is down to {$this->quantity} unit(s). Its reorder level is {$this->reorderLevel}.")
            ->line('Suggested action: reorder this item.')
            ->action('View product', route('products.show', $this->productId));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'reorder_level' => $this->reorderLevel,
            'action' => 'reorder',
        ];
    }
}