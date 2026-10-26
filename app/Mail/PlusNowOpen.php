<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells a "Beritahu saya" sign-up that Langganan Plus is now on sale. */
class PlusNowOpen extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Langganan Plus ASUH kini dibuka'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.plus-now-open');
    }
}
