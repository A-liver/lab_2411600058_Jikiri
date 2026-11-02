<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param array $items       rows: name, sku, stock, reorder_point, quantity, supplier
     * @param array $references  reference numbers of the draft purchase orders that were created
     */
    public function __construct(public array $items, public array $references) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Low stock alert: ' . count($this->items) . ' product(s) need reordering');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.low-stock-alert');
    }

    public function attachments(): array
    {
        return [];
    }
}