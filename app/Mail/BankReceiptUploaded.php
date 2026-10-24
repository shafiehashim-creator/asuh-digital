<?php

namespace App\Mail;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the admin(s) in the admin_notification_emails setting that a bank
 * transfer receipt is waiting. The receipt itself is not attached: it may
 * only be opened inside the admin panel.
 */
class BankReceiptUploaded extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Resit pindahan bank baharu: '.$this->order->reference);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.bank-receipt-uploaded', with: [
            'order' => $this->order->loadMissing('user'),
            'url' => OrderResource::getUrl('view', ['record' => $this->order], panel: 'admin'),
        ]);
    }
}
