<?php

namespace App\Models;

use App\Enums\CampaignOffer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A promotion with a countdown (a pre-order, a launch, a sale). It can also
 * set the price between its start and end (offer): the Langganan Tahunan
 * early bird or a promotional Langganan Plus price. Before it starts it can
 * show as "coming soon" while buyers pay the normal price.
 */
#[Fillable(['title', 'title_en', 'message', 'message_en', 'cta_label', 'cta_label_en', 'cta_url', 'promo_code', 'offer', 'promo_price_sen', 'starts_at', 'ends_at', 'show_bar', 'show_banner', 'show_coming_soon', 'is_active'])]
class Campaign extends Model
{
    protected function casts(): array
    {
        return [
            'offer' => CampaignOffer::class,
            'promo_price_sen' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'show_bar' => 'boolean',
            'show_banner' => 'boolean',
            'show_coming_soon' => 'boolean',
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

    /** Switched on and announced as "coming soon" before it starts. */
    public function scopeComingSoon(Builder $query): void
    {
        $query->where('is_active', true)->where('show_coming_soon', true)->where('starts_at', '>', now());
    }

    public function isUpcoming(): bool
    {
        return (bool) $this->starts_at?->isFuture();
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

    /** Seconds to the moment the countdown counts to: the start while upcoming, else the end. */
    public function secondsLeft(): int
    {
        $target = $this->isUpcoming() ? $this->starts_at : $this->ends_at;

        return max(0, (int) now()->diffInSeconds($target, false));
    }
}
