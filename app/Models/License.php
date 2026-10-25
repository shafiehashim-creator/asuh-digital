<?php

namespace App\Models;

use App\Enums\LicenseKind;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Created by the ASUH admin once an institution pays (invoice or bank
 * transfer outside the site) or starts a free pilot. Opens every published
 * book to the institution's pupils, up to max_students names.
 */
#[Fillable(['institution_id', 'kind', 'max_students', 'starts_on', 'ends_on', 'notes', 'includes_videos'])]
class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => LicenseKind::class,
            'max_students' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'includes_videos' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today());
    }

    public function isActive(): bool
    {
        return $this->starts_on->lte(today()) && $this->ends_on->gte(today());
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
