<?php

namespace App\Models;

use App\Enums\InstitutionType;
use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'state', 'district', 'pilot_request_id'])]
class Institution extends Model
{
    /** @use HasFactory<InstitutionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['type' => InstitutionType::class];
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
