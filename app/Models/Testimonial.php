<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** A parent's words or WhatsApp screenshot, shown on the yearly subscription sales page. */
class Testimonial extends Model
{
    /** The sales page shows the testimonials section only from this many. */
    public const MIN_SHOWN = 2;

    protected $fillable = ['author', 'detail', 'quote', 'quote_en', 'image_path', 'is_published', 'sort_order'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->orderBy('sort_order')->latest('id');
    }

    /** The quote in the visitor's language, falling back to BM. */
    public function localQuote(): ?string
    {
        return app()->getLocale() === 'en' && filled($this->quote_en) ? $this->quote_en : $this->quote;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
