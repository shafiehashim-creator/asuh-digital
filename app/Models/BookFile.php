<?php

namespace App\Models;

use App\Enums\BookEdition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['book_id', 'edition', 'path', 'page_count', 'size_bytes'])]
class BookFile extends Model
{
    /** Private disk: storage/app/private, served only by ReaderController. */
    public const DISK = 'local';

    /** Width of the light page images ("versi pantas"), in pixels. */
    public const PAGE_IMAGE_WIDTH = 1400;

    public const PAGE_FORMATS = ['webp' => 'image/webp', 'jpg' => 'image/jpeg'];

    protected static function booted(): void
    {
        // Page images belong to one PDF: a new upload or a deleted file makes them stale.
        static::updating(function (BookFile $file) {
            if ($file->isDirty('path')) {
                $file->forgetPageImages();
            }
        });

        static::deleted(fn (BookFile $file) => Storage::disk(self::DISK)->deleteDirectory($file->pageDirectory()));
    }

    protected function casts(): array
    {
        return [
            'edition' => BookEdition::class,
            'page_count' => 'integer',
            'size_bytes' => 'integer',
            'page_images' => 'integer',
            'page_width' => 'integer',
            'page_height' => 'integer',
            'pages_ready_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function hasPageImages(): bool
    {
        return $this->pages_ready_at !== null && $this->page_images > 0;
    }

    public function pageDirectory(): string
    {
        return 'book-pages/'.$this->id;
    }

    public function pageImagePath(int $page, ?string $format = null): string
    {
        return $this->pageDirectory().'/'.$page.'.'.($format ?? $this->page_format);
    }

    /** Clears the attributes; the caller saves. Old images are removed at once. */
    public function forgetPageImages(): void
    {
        Storage::disk(self::DISK)->deleteDirectory($this->pageDirectory());

        $this->forceFill([
            'page_images' => null,
            'page_format' => null,
            'page_width' => null,
            'page_height' => null,
            'pages_ready_at' => null,
        ]);
    }
}
