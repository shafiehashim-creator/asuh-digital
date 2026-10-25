<?php

namespace App\Models;

use App\Enums\InstitutionType;
use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'type', 'state', 'district', 'pilot_request_id', 'parent_code', 'parent_discount_percent'])]
class Institution extends Model
{
    /** @use HasFactory<InstitutionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Institution $institution) {
            $institution->parent_code ??= static::newParentCode();
        });

        static::saving(function (Institution $institution) {
            $institution->parent_code = $institution->parent_code ? Str::upper(trim($institution->parent_code)) : null;
        });
    }

    protected function casts(): array
    {
        return ['type' => InstitutionType::class, 'parent_discount_percent' => 'integer'];
    }

    public static function newParentCode(): string
    {
        do {
            $code = 'ASUH-'.Str::upper(Str::random(5));
        } while (static::query()->where('parent_code', $code)->exists());

        return $code;
    }

    /** The school whose parent discount code this is, or null. */
    public static function findByParentCode(?string $code): ?self
    {
        $code = Str::upper(trim((string) $code));

        return $code === '' ? null : static::query()->where('parent_code', $code)->where('parent_discount_percent', '>', 0)->first();
    }

    /** Family orders that came with this school's code. */
    public function referredOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'referral_institution_id');
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class)->orderByDesc('ends_on');
    }

    /** The licence in force today, the one ending last if several overlap. */
    public function activeLicense(): ?License
    {
        return $this->licenses()->active()->first();
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class)->orderBy('name');
    }

    /** Pupils in all classes, counted against the licence's max_students. */
    public function pupilCount(): int
    {
        return Child::query()->whereIn('classroom_id', $this->classrooms()->select('id'))->count();
    }

    /** Phase 2: the active licence includes the animated videos. */
    public function canWatchVideos(): bool
    {
        return (bool) $this->activeLicense()?->includes_videos;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class)->latest();
    }

    public function pilotRequest(): BelongsTo
    {
        return $this->belongsTo(PilotRequest::class);
    }
}
