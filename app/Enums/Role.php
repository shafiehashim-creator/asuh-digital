<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasLabel
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Parent = 'parent';
    case InstitutionAdmin = 'institution_admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Super Admin',
            self::Editor => 'Editor',
            self::Parent => 'Ibu bapa',
            self::InstitutionAdmin => 'Admin institusi',
        };
    }

    public function canAccessAdminPanel(): bool
    {
        return in_array($this, [self::Admin, self::Editor], true);
    }
}
