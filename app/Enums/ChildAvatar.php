<?php

namespace App\Enums;

/**
 * Avatars a child can pick. Drawn pictures only: children's photos are never
 * stored (CLAUDE.md, PDPA).
 */
enum ChildAvatar: string
{
    case Anis = 'anis';
    case Anas = 'anas';
    case Kucing = 'kucing';
    case Arnab = 'arnab';
    case Penyu = 'penyu';
    case RamaRama = 'rama-rama';
    case Unta = 'unta';
    case Bintang = 'bintang';

    public function label(): string
    {
        return match ($this) {
            self::Anis => 'Anis',
            self::Anas => 'Anas',
            self::Kucing => 'Kucing',
            self::Arnab => 'Arnab',
            self::Penyu => 'Penyu',
            self::RamaRama => 'Rama-rama',
            self::Unta => 'Unta',
            self::Bintang => 'Bintang',
        };
    }

    /** Brand characters have a picture; the rest show an emoji. */
    public function image(): ?string
    {
        return match ($this) {
            self::Anis => 'resources/images/brand/anis.webp',
            self::Anas => 'resources/images/brand/anas.webp',
            default => null,
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Kucing => '🐱',
            self::Arnab => '🐰',
            self::Penyu => '🐢',
            self::RamaRama => '🦋',
            self::Unta => '🐪',
            self::Bintang => '⭐',
            default => '',
        };
    }
}
