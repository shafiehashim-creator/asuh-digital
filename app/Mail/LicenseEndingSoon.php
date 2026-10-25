<?php

namespace App\Mail;

use App\Models\License;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LicenseEndingSoon extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public License $license) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Lesen ASUH Digital '.$this->license->institution->name.' akan tamat',
            replyTo: array_filter([
                Setting::get('contact_email') ? new Address(Setting::get('contact_email')) : null,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.license-ending-soon', with: [
            'institution' => $this->license->institution,
            'days' => (int) today()->diffInDays($this->license->ends_on),
            'whatsapp' => Setting::get('whatsapp_number'),
        ]);
    }
}
