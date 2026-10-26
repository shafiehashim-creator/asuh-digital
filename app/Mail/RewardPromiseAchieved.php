<?php

namespace App\Mail;

use App\Models\RewardPromise;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells a parent their child has earned the badge they promised a reward for. */
class RewardPromiseAchieved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public RewardPromise $promise) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->promise->child->nickname.' mencapai janji ganjaran · ASUH Digital');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reward-promise-achieved');
    }
}
