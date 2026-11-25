<?php

namespace App\Jobs;

use App\Enums\AudioStatus;
use App\Models\BookPage;
use App\Services\Audio\BookAudio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Makes the AI voice of one book page (queue: cron runs the worker every minute). */
class GeneratePageAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public BookPage $page) {}

    public function handle(BookAudio $audio): void
    {
        $audio->generate($this->page);
    }

    public function failed(?Throwable $e): void
    {
        $this->page->forceFill([
            'audio_status' => $this->page->audio_path ? AudioStatus::Ready : AudioStatus::Failed,
            'audio_error' => 'Suara AI gagal dijana. Cuba lagi.',
        ])->save();
    }
}
