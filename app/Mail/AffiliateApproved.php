<?php

namespace App\Mail;

use App\Models\Affiliate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AffiliateApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Affiliate $affiliate) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Anda kini affiliate ASUH Digital');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.affiliate-approved', with: [
            'affiliate' => $this->affiliate,
            'url' => route('account.affiliate'),
        ]);
    }
}
