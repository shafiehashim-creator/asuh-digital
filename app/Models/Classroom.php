<?php

namespace App\Models;

use App\Enums\AgeGroup;
use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['institution_id', 'teacher_id', 'name', 'age_group', 'code'])]
class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    /** No 0/O, 1/I/L or 5/S, so children copying the code from the board do not mix them up. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRTUVWXYZ2346789';

    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom) {
            $classroom->code ??= static::newCode();
        });
    }

    protected function casts(): array
    {
        return ['age_group' => AgeGroup::class];
    }

    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    /** Codes are typed by children: ignore case, spaces and dashes. */
    public static function findByCode(string $code): ?self
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return strlen($code) === 6 ? static::query()->where('code', $code)->first() : null;
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function pupils(): HasMany
    {
        return $this->hasMany(Child::class)->orderBy('nickname');
    }
}
