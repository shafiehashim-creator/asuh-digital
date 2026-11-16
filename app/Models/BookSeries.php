<?php

namespace App\Models;

use App\Enums\BookStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A series of books read in order (Buku 1, Buku 2 …). The catalogue shows a
 * series as one card that opens /siri/{slug}, the book page lists the other
 * books in the series, and Mod Anak gives each series its own shelf row.
 */
#[Fillable(['name', 'name_en', 'slug', 'description', 'description_en'])]
class BookSeries extends Model
{
    use HasFactory;

    protected $table = 'book_series';

    protected static function booted(): void
    {
        static::creating(function (BookSeries $series) {
            if (blank($series->slug)) {
                $base = Str::slug($series->name) ?: 'siri';
                $slug = $base;
                for ($i = 2; static::query()->where('slug', $slug)->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }
                $series->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** All books in reading order, drafts included (for staff). */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class)->orderByRaw('series_position is null')->orderBy('series_position')->orderBy('id');
    }

    public function publishedBooks(): HasMany
    {
        return $this->books()->where('status', BookStatus::Published)->where('published_at', '<=', now());
    }

    public function localName(): string
    {
        return app()->getLocale() === 'en' && filled($this->name_en) ? $this->name_en : $this->name;
    }

    public function localDescription(): ?string
    {
        return app()->getLocale() === 'en' && filled($this->description_en) ? $this->description_en : $this->description;
    }
}
