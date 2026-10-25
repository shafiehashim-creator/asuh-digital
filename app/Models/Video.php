<?php

namespace App\Models;

use App\Enums\AgeGroup;
use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Enums\VideoProvider;
use App\Exceptions\ContentNotApproved;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * An animated video (Phase 2), linked to a book or standing alone. The
 * provider's id is hidden from serialisation: it reaches a browser only
 * through the player, after the access check.
 */
#[Fillable([
    'title', 'slug', 'synopsis', 'age_group', 'book_id', 'provider', 'provider_video_id',
    'duration_seconds', 'thumbnail_path', 'status', 'published_at',
])]
#[Hidden(['provider_video_id'])]
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'age_group' => AgeGroup::class,
            'provider' => VideoProvider::class,
            // Same draft -> review -> published states as books.
            'status' => BookStatus::class,
            'duration_seconds' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Video $video) {
            $video->slug = filled($video->slug) ? $video->slug : static::uniqueSlug($video->title);
        });

        static::saving(function (Video $video) {
            if ($video->status !== BookStatus::Published) {
                return;
            }

            if (! $video->exists || ! $video->hasApprovedReview()) {
                throw ContentNotApproved::video();
            }

            $video->published_at ??= now();
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'video';
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

    public function scopePublished(Builder $query): void
    {
        $query->where('status', BookStatus::Published)->where('published_at', '<=', now());
    }

    public function scopeForAge(Builder $query, AgeGroup $age): void
    {
        $query->whereIn('age_group', $age === AgeGroup::Both ? AgeGroup::cases() : [$age, AgeGroup::Both]);
    }

    public function isPublished(): bool
    {
        return $this->status === BookStatus::Published && $this->published_at?->isPast();
    }

    public function hasApprovedReview(): bool
    {
        return $this->latestReview()->first()?->status === ReviewStatus::Approved;
    }

    /** "1:05" style length, or null when not known. */
    public function durationLabel(): ?string
    {
        return $this->duration_seconds
            ? intdiv($this->duration_seconds, 60).':'.str_pad((string) ($this->duration_seconds % 60), 2, '0', STR_PAD_LEFT)
            : null;
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->orderBy('sort_order');
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
