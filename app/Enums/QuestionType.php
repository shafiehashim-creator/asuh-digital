<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Kinds of question in a Kuiz Interaktif. */
enum QuestionType: string implements HasLabel
{
    case Choice = 'pilihan';
    case TrueFalse = 'betul_salah';
    case Order = 'susun';
    case Fill = 'isi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Choice => 'Aneka pilihan',
            self::TrueFalse => 'Betul atau salah',
            self::Order => 'Susun ikut tertib',
            self::Fill => 'Isi tempat kosong',
        };
    }
}
