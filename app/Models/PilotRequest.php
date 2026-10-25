<?php

namespace App\Models;

use App\Enums\AgeGroup;
use App\Enums\InstitutionType;
use App\Enums\PilotStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'institution_name', 'institution_type', 'state', 'district', 'contact_name',
    'contact_position', 'email', 'phone', 'student_count', 'age_group', 'notes', 'status',
])]
class PilotRequest extends Model
{
    use HasFactory;

    public function institution(): HasOne
    {
        return $this->hasOne(Institution::class);
    }

    protected function casts(): array
    {
        return [
            'institution_type' => InstitutionType::class,
            'age_group' => AgeGroup::class,
            'status' => PilotStatus::class,
            'student_count' => 'integer',
        ];
    }
}
