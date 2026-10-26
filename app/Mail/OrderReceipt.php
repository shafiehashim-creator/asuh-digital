<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once when an order becomes paid (via the OrderPaid event).
 * Queued: the cPanel cron runs the database queue every minute.
 */
class OrderReceipt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Resit pembayaran :reference', ['reference' => $this->order->reference]).' · ASUH Digital',
            replyTo: array_filter([
                Setting::get('contact_email') ? new Address(Setting::get('contact_email')) : null,
            ]),
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing(['user', 'plan', 'pass', 'confirmedBy']);

        return new Content(
            markdown: 'mail.order-receipt',
            with: [
                'order' => $this->order,
                'pass' => $this->order->pass,
                'company' => [
                    'name' => Setting::get('company_name'),
                    'ssm' => Setting::get('company_ssm_no'),
                    'address' => Setting::get('company_address'),
                    'sst' => Setting::get('company_sst_no'),
                    'email' => Setting::get('contact_email'),
                ],
            ],
        );
    }
}
