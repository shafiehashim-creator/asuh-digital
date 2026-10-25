<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasLabel
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Parent = 'parent';
    case InstitutionAdmin = 'institution_admin';
    case Teacher = 'teacher';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Super Admin',
            self::Editor => 'Editor',
            self::Parent => 'Ibu bapa',
            self::InstitutionAdmin => 'Pentadbir institusi',
            self::Teacher => 'Guru',
        };
    }

    /** Institution staff use the teacher portal (/guru), not the ASUH admin panel. */
    public function isInstitutionStaff(): bool
    {
        return in_array($this, [self::InstitutionAdmin, self::Teacher], true);
    }

    public function canAccessAdminPanel(): bool
    {
        return in_array($this, [self::Admin, self::Editor], true);
    }
}
