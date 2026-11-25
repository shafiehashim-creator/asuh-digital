<?php

namespace App\Jobs;

use App\Models\BookPage;
use App\Services\Audio\BookAudio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Finds when each word is said in an uploaded voice, for the word highlight. */
class AlignPageAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public BookPage $page) {}

    public function handle(BookAudio $audio): void
    {
        $audio->align($this->page);
    }

    public function failed(?Throwable $e): void
    {
        $this->page->forceFill(['audio_error' => 'Masa perkataan tidak dapat dikira. Audio tetap boleh dimainkan tanpa serlahan.'])->save();
    }
}
