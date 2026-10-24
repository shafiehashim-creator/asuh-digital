<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the buyer we could not confirm their transfer. The admin's reason is
 * internal and deliberately not included.
 */
class BankTransferRejected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pindahan bank belum dapat disahkan: '.$this->order->reference);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.bank-transfer-rejected', with: [
            'order' => $this->order->loadMissing('user'),
            'contactEmail' => Setting::get('contact_email'),
        ]);
    }
}
