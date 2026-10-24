<?php

namespace App\Mail;

use App\Filament\Resources\PilotRequests\PilotRequestResource;
use App\Models\PilotRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the admin(s) in the admin_notification_emails setting.
 */
class PilotRequestReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PilotRequest $pilot) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Permohonan pilot baharu: '.$this->pilot->institution_name,
            // Reply goes straight to the institution's contact person.
            replyTo: [new Address($this->pilot->email, $this->pilot->contact_name)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.pilot-request-received', with: [
            'pilot' => $this->pilot,
            'url' => PilotRequestResource::getUrl('view', ['record' => $this->pilot], panel: 'admin'),
        ]);
    }
}
