<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Weekly reading report to a parent (asuh:weekly-reports). Queued: the cPanel
 * cron runs the database queue every minute.
 */
class WeeklyReadingReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $children  from WeeklyReport::forParent()
     */
    public function __construct(public User $parent, public array $children, public Carbon $weekStart, public Carbon $weekEnd) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Laporan bacaan mingguan anak anda · ASUH Digital');
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>']);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.weekly-reading-report',
            with: ['unsubscribeUrl' => $this->unsubscribeUrl()],
        );
    }

    private function unsubscribeUrl(): string
    {
        return URL::signedRoute('weekly-report.unsubscribe', ['user' => $this->parent->id]);
    }
}
