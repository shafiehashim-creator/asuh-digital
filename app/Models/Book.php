<?php

namespace App\Models;

use App\Enums\AgeGroup;
use App\Enums\BookEdition;
use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\BookNotApproved;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'title', 'slug', 'author', 'synopsis', 'age_group', 'single_price_sen',
    'is_enhanced_edition', 'cover_path', 'status', 'published_at',
])]
class Book extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'age_group' => AgeGroup::class,
            'status' => BookStatus::class,
            'single_price_sen' => 'integer',
            'is_enhanced_edition' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Book $book) {
            $book->slug = filled($book->slug) ? $book->slug : static::uniqueSlug($book->title);
        });

        static::saving(function (Book $book) {
            if ($book->status !== BookStatus::Published) {
                return;
            }

            // The rule is enforced here so no screen or script can skip it.
            if (! $book->exists || ! $book->hasApprovedReview()) {
                throw BookNotApproved::make();
            }

            $book->published_at ??= now();
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'buku';
        $slug = $base;

        for ($i = 2; static::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Visible to readers: published, with a publish date that has arrived.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', BookStatus::Published)->where('published_at', '<=', now());
    }

    /**
     * Books suitable for a child's age group (books for both ages included).
     */
    public function scopeForAge(Builder $query, AgeGroup $age): void
    {
        $query->whereIn('age_group', $age === AgeGroup::Both
            ? AgeGroup::cases()
            : [$age, AgeGroup::Both]);
    }

    public function hasApprovedReview(): bool
    {
        return $this->latestReview()->first()?->status === ReviewStatus::Approved;
    }

    public function isPublished(): bool
    {
        return $this->status === BookStatus::Published && $this->published_at?->isPast();
    }

    /**
     * The file to read: the enhanced edition when the book has one, else the original.
     */
    public function readableFile(): ?BookFile
    {
        $files = $this->files->keyBy(fn (BookFile $file) => $file->edition->value);

        return ($this->is_enhanced_edition ? $files->get(BookEdition::Enhanced->value) : null)
            ?? $files->get(BookEdition::Original->value);
    }

    /** A book may sit in several categories, shown in the admin's order. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->orderBy('sort_order');
    }

    /**
     * Pages anyone may read free: the first few "versi pantas" images (a PDF
     * cannot be cut short safely), so 0 until those exist or when turned off.
     */
    public function previewPageCount(): int
    {
        $file = $this->readableFile();

        if (! $this->isPublished() || ! $file?->hasPageImages()) {
            return 0;
        }

        return min((int) Setting::get('preview_pages'), $file->page_images);
    }

    /** Families who have opened this book in the reader (staff previews excluded). */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'book_readers')->withPivot('first_read_at', 'last_read_at');
    }

    public function recordReader(User $user): void
    {
        if ($user->isStaff()) {
            return;
        }

        $now = now();
        DB::table('book_readers')->upsert(
            [['book_id' => $this->id, 'user_id' => $user->id, 'first_read_at' => $now, 'last_read_at' => $now]],
            ['book_id', 'user_id'],
            ['last_read_at'],
        );
    }

    public function files(): HasMany
    {
        return $this->hasMany(BookFile::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SyariahReview::class)->latest();
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(SyariahReview::class)->latestOfMany();
    }
}
