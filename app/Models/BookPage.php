<?php

namespace App\Models;

use App\Enums\AudioStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One page of a book for the audiobook ("Baca bersama"): its text, the
 * position of each word on the page (from the PDF), its voice and when each
 * word is spoken. See App\Services\Audio\BookAudio.
 */
#[Fillable(['book_id', 'page', 'text', 'word_boxes', 'audio_path', 'audio_source', 'audio_status', 'audio_error', 'audio_seconds', 'timings'])]
class BookPage extends Model
{
    public const DISK = 'local';

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'word_boxes' => 'array',
            'timings' => 'array',
            'audio_status' => AudioStatus::class,
            'audio_seconds' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // The voice file goes with its page.
        static::deleting(fn (BookPage $page) => $page->deleteAudioFile());
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function hasAudio(): bool
    {
        return $this->audio_status === AudioStatus::Ready && filled($this->audio_path);
    }

    public function hasText(): bool
    {
        return filled(trim((string) $this->text));
    }

    public function audioDirectory(): string
    {
        return "book-audio/{$this->book_id}";
    }

    public function deleteAudioFile(): void
    {
        if ($this->audio_path) {
            Storage::disk(self::DISK)->delete($this->audio_path);
        }
    }
}
