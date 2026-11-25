<?php

namespace App\Services\Audio;

use App\Enums\AudioStatus;
use App\Jobs\AlignPageAudio;
use App\Jobs\GeneratePageAudio;
use App\Models\Book;
use App\Models\BookPage;
use App\Models\Child;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The audiobook ("Baca bersama"): each page of a book can have a voice,
 * recorded and uploaded or made by AI, and the time each word is spoken.
 * The reader plays a page's voice, highlights the word being read and turns
 * the page by itself. Hidden from the public until `audio_open` is on and
 * the book's audio is published; staff can always preview.
 */
class BookAudio
{
    public function __construct(private ElevenLabs $voices) {}

    /** Readers see audio once the switch is on and the book's audio is published (staff: always). */
    public function availableFor(Book $book, ?User $user, ?Child $child): bool
    {
        if ($child && ! $child->allow_audio) {
            return false;
        }
        if ($user?->isStaff()) {
            return true;
        }

        return Setting::get('audio_open') && $book->audio_published_at !== null;
    }

    /** Ids of the given books that have published audio, for the 🎧 badges. */
    public function bookIdsWithAudio(iterable $bookIds): array
    {
        if (! Setting::get('audio_open')) {
            return [];
        }

        return Book::query()->whereIn('id', collect($bookIds)->all())->whereNotNull('audio_published_at')
            ->whereHas('pages', fn ($q) => $q->where('audio_status', AudioStatus::Ready))
            ->pluck('id')->all();
    }

    /** One row per page of the book's PDF, so staff can add text and voices. */
    public function ensurePages(Book $book): int
    {
        $file = $book->readableFile();
        $count = (int) ($file?->page_images ?: $file?->page_count);
        $existing = $book->pages()->pluck('page')->all();
        foreach (range(1, max($count, 0)) as $page) {
            if ($count && ! in_array($page, $existing, true)) {
                $book->pages()->create(['page' => $page]);
            }
        }

        return $count;
    }

    /**
     * Text and word positions read from the PDF while "versi pantas" runs.
     * Text staff have already edited is kept; positions are always refreshed.
     *
     * @param  list<array{t: string, x: float, y: float, w: float, h: float}>  $boxes
     */
    public function savePdfWords(Book $book, int $page, array $boxes): void
    {
        $row = $book->pages()->firstOrCreate(['page' => $page]);
        $row->word_boxes = $boxes ?: null;
        if (! $row->hasText() && $boxes) {
            $row->text = Str::limit(implode(' ', array_column($boxes, 't')), 5000, '');
        }
        $row->save();
    }

    /** A recorded (or downloaded) voice for one page. */
    public function storeUpload(BookPage $page, UploadedFile|string $file): void
    {
        $page->deleteAudioFile();
        $name = $page->page.'-'.Str::lower(Str::random(8)).'.mp3';
        $path = $file instanceof UploadedFile
            ? $file->storeAs($page->audioDirectory(), $name, BookPage::DISK)
            : (Storage::disk(BookPage::DISK)->putFileAs($page->audioDirectory(), $file, $name) ?: null);

        $page->forceFill([
            'audio_path' => $path,
            'audio_source' => 'upload',
            'audio_status' => AudioStatus::Ready,
            'audio_error' => null,
            'audio_seconds' => null,
            'timings' => null,
        ])->save();

        // The word highlight needs the text and the times each word is said.
        if ($page->hasText() && ElevenLabs::configured()) {
            AlignPageAudio::dispatch($page);
        }
    }

    /** Queues AI voices for the given pages (those with text). Returns how many. */
    public function queueGeneration(iterable $pages): int
    {
        $queued = 0;
        foreach ($pages as $page) {
            if (! $page->hasText()) {
                continue;
            }
            $page->forceFill(['audio_status' => AudioStatus::Queued, 'audio_error' => null])->save();
            GeneratePageAudio::dispatch($page);
            $queued++;
        }

        return $queued;
    }

    public function generate(BookPage $page): void
    {
        $result = $this->voices->speak(trim((string) $page->text));

        $page->deleteAudioFile();
        $path = $page->audioDirectory().'/'.$page->page.'-ai-'.Str::lower(Str::random(8)).'.mp3';
        Storage::disk(BookPage::DISK)->put($path, $result['audio']);

        $page->forceFill([
            'audio_path' => $path,
            'audio_source' => 'ai',
            'audio_status' => AudioStatus::Ready,
            'audio_error' => null,
            'timings' => $result['words'] ?: null,
            'audio_seconds' => $result['words'] ? end($result['words'])['e'] : null,
        ])->save();
    }

    public function align(BookPage $page): void
    {
        if (! $page->hasAudio() || ! $page->hasText()) {
            return;
        }

        $words = $this->voices->align(Storage::disk(BookPage::DISK)->path($page->audio_path), trim((string) $page->text));
        $page->forceFill([
            'timings' => $words ?: null,
            'audio_seconds' => $words ? end($words)['e'] : $page->audio_seconds,
        ])->save();
    }

    public function removeAudio(BookPage $page): void
    {
        $page->deleteAudioFile();
        $page->forceFill(['audio_path' => null, 'audio_source' => null, 'audio_status' => AudioStatus::None, 'audio_error' => null, 'audio_seconds' => null, 'timings' => null])->save();
    }

    /**
     * The page number in a file name such as "halaman-03.mp3", "p3.mp3" or
     * "Buku A 12.mp3" (the last number), or null.
     */
    public static function pageFromFileName(string $name): ?int
    {
        return preg_match_all('/\d+/', pathinfo($name, PATHINFO_FILENAME), $m) ? ((int) end($m[0]) ?: null) : null;
    }

    /**
     * What the reader needs for each page with a voice: the words with their
     * times and, where the PDF has real text, their place on the page.
     *
     * @return array<int, list<array{t: string, s: float, e: float, b: ?list<float>}>>
     */
    public function readerPages(Book $book): array
    {
        return $book->pages()->where('audio_status', AudioStatus::Ready)->whereNotNull('audio_path')->get()
            ->mapWithKeys(fn (BookPage $page) => [$page->page => WordMatcher::attachBoxes($page->timings ?? [], $page->word_boxes ?? [])])
            ->all();
    }
}
