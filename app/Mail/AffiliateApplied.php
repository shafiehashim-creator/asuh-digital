<?php

namespace App\Mail;

use App\Filament\Resources\Affiliates\AffiliateResource;
use App\Models\Affiliate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the admin(s) in the admin_notification_emails setting.
 */
class AffiliateApplied extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Affiliate $affiliate) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Permohonan affiliate baharu: '.$this->affiliate->user->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.affiliate-applied', with: [
            'affiliate' => $this->affiliate,
            'url' => AffiliateResource::getUrl(panel: 'admin'),
        ]);
    }
}
