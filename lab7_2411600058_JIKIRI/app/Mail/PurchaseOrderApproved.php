<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $order, public User $approver) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Approved: purchase order {$this->order->reference_number}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.purchase-order-approved');
    }

    public function attachments(): array
    {
        return [];
    }
}