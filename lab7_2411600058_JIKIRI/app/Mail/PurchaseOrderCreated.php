<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;
use App\Services\ExportService;
class PurchaseOrderCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Approval needed: purchase order {$this->order->reference_number}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.purchase-order-created');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => app(ExportService::class)->purchaseOrderPdf($this->order)->output(),
                $this->order->reference_number . '.pdf'
            )->withMime('application/pdf'),
        ];
    }
}