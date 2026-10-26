<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A promotion with a countdown to its end date (a pre-order, a launch, a
 * sale). It only shows the countdown: the offer itself (a coupon, the early
 * bird price) has its own dates in the admin.
 */
#[Fillable(['title', 'title_en', 'message', 'message_en', 'cta_label', 'cta_label_en', 'cta_url', 'promo_code', 'starts_at', 'ends_at', 'show_bar', 'show_banner', 'is_active'])]
class Campaign extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'show_bar' => 'boolean',
            'show_banner' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Switched on, started and not yet ended. */
    public function scopeRunning(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where('ends_at', '>', now());
    }

    public function status(): string
    {
        return match (true) {
            $this->ends_at->isPast() => 'Tamat',
            $this->starts_at?->isFuture() => 'Akan datang',
            ! $this->is_active => 'Dimatikan',
            default => 'Berjalan',
        };
    }

    /** The English text when the visitor reads in English and it is filled in. */
    public function localized(string $field): ?string
    {
        $english = app()->getLocale() === 'en' ? $this->{$field.'_en'} : null;

        return filled($english) ? $english : $this->{$field};
    }

    public function secondsLeft(): int
    {
        return max(0, (int) now()->diffInSeconds($this->ends_at, false));
    }
}
