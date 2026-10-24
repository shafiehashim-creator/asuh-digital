<?php

namespace App\Mail;

use App\Models\PilotRequest;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PilotRequestConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PilotRequest $pilot) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Permohonan pilot ASUH Digital diterima',
            replyTo: array_filter([
                Setting::get('contact_email') ? new Address(Setting::get('contact_email')) : null,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.pilot-request-confirmation', with: [
            'pilot' => $this->pilot,
            'pilotDays' => (int) Setting::get('pilot_duration_days'),
        ]);
    }
}
