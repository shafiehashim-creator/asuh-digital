<?php

namespace App\Models;

use App\Enums\LicenseKind;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Created by the ASUH admin once an institution pays (invoice or bank
 * transfer outside the site) or starts a free pilot. Opens every published
 * book to the institution's pupils, up to max_students names.
 */
#[Fillable(['institution_id', 'kind', 'max_students', 'starts_on', 'ends_on', 'notes', 'includes_videos', 'access_days', 'access_starts', 'access_ends'])]
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
            'access_days' => 'array',
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

    /** Malay day names, ISO numbering (1 = Monday). */
    public const DAYS = [1 => 'Isnin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Khamis', 5 => 'Jumaat', 6 => 'Sabtu', 7 => 'Ahad'];

    public function hasSchoolHours(): bool
    {
        return ! empty($this->access_days);
    }

    /** Pupils may read now: any time, or within the school days and hours. */
    public function isOpenNow(): bool
    {
        if (! $this->hasSchoolHours()) {
            return true;
        }

        $now = now();
        $time = $now->format('H:i:s');

        return in_array($now->dayOfWeekIso, array_map('intval', $this->access_days), true)
            && (! $this->access_starts || $time >= $this->normaliseTime($this->access_starts))
            && (! $this->access_ends || $time < $this->normaliseTime($this->access_ends));
    }

    /** "Isnin–Jumaat, 7:00 pagi – 6:00 petang" style, for teachers and pupils. */
    public function schoolHoursLabel(): ?string
    {
        if (! $this->hasSchoolHours()) {
            return null;
        }

        $days = collect($this->access_days)->map(fn ($d) => (int) $d)->sort()->values();
        $consecutive = $days->count() > 2 && $days->last() - $days->first() === $days->count() - 1;
        $dayText = $consecutive
            ? self::DAYS[$days->first()].'–'.self::DAYS[$days->last()]
            : $days->map(fn ($d) => self::DAYS[$d])->join(', ');

        $time = fn (?string $t) => $t ? Carbon::parse($t)->translatedFormat('g:i A') : null;
        $hours = $this->access_starts || $this->access_ends
            ? ', '.($time($this->access_starts) ?? '12:00 tengah malam').' – '.($time($this->access_ends) ?? '11:59 malam')
            : '';

        return $dayText.$hours;
    }

    private function normaliseTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
