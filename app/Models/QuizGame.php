<?php

namespace App\Models;

use App\Enums\AgeGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A Kuiz Interaktif made by ASUH (see App\Services\QuizGames). */
#[Fillable(['title', 'slug', 'description', 'cover_path', 'age_group', 'category_id', 'seconds_per_question', 'published_at', 'created_by'])]
class QuizGame extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'age_group' => AgeGroup::class,
            'seconds_per_question' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QuizGame $quiz) {
            if (blank($quiz->slug)) {
                $base = Str::slug($quiz->title) ?: 'kuiz';
                $slug = $base;
                for ($i = 2; static::query()->where('slug', $slug)->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }
                $quiz->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizGameQuestion::class)->orderBy('sort')->orderBy('id');
    }

    public function plays(): HasMany
    {
        return $this->hasMany(QuizGamePlay::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(QuizGameAssignment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeForAge(Builder $query, AgeGroup $age): void
    {
        $query->whereIn('age_group', $age === AgeGroup::Both ? AgeGroup::cases() : [$age, AgeGroup::Both]);
    }
}
