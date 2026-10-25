<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstitutionInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Jemputan ke ASUH Digital untuk '.$this->invitation->institution->name);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.institution-invitation',
            with: [
                'url' => route('invitation.show', $this->token),
                'institution' => $this->invitation->institution,
                'days' => Invitation::VALID_DAYS,
            ],
        );
    }
}
