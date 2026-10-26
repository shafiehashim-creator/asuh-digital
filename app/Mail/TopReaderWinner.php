<?php

namespace App\Mail;

use App\Models\MonthlyTopReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells a parent their child is one of ASUH's 5 best readers of the month. */
class TopReaderWinner extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public MonthlyTopReader $winner) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tahniah! '.$this->winner->child->nickname.' antara 5 pembaca terbaik ASUH');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.top-reader-winner');
    }
}
