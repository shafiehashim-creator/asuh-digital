<?php

namespace App\Enums;

/**
 * The games in Mod Anak (user decision 2026-10-27), for children of
 * subscribing families. Each plays in the browser from the content in
 * App\Support\GameContent; the slug is its address and its Alpine component.
 */
enum Game: string
{
    case Memory = 'padan-kad';
    case GoodChoice = 'pilih-yang-baik';
    case Order = 'susun-tertib';
    case Hijaiyah = 'huruf-hijaiyah';
    case WordSearch = 'cari-perkataan';
    case Prophets = 'teka-nabi';
    case QuickQuiz = 'kuiz-pantas';

    public function label(): string
    {
        return match ($this) {
            self::Memory => 'Padan Kad Islamik',
            self::GoodChoice => 'Pilih yang Baik',
            self::Order => 'Susun Ikut Tertib',
            self::Hijaiyah => 'Kenal Huruf Hijaiyah',
            self::WordSearch => 'Cari Perkataan',
            self::Prophets => 'Teka Nabi dan Rasul',
            self::QuickQuiz => 'Kuiz Pantas Adab',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Memory => '🃏',
            self::GoodChoice => '😇',
            self::Order => '🔢',
            self::Hijaiyah => 'ب',
            self::WordSearch => '🔎',
            self::Prophets => '🌙',
            self::QuickQuiz => '⏱️',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Memory => 'Balikkan kad dan cari pasangannya.',
            self::GoodChoice => 'Bantu Anis dan Anas pilih yang baik.',
            self::Order => 'Tekan ikut urutan yang betul.',
            self::Hijaiyah => 'Lihat huruf, pilih namanya.',
            self::WordSearch => 'Cari perkataan yang tersembunyi.',
            self::Prophets => 'Baca petunjuk, teka nabinya.',
            self::QuickQuiz => 'Jawab cepat sebelum masa tamat!',
        };
    }

    /** The Alpine component that plays it (resources/js/games.js). */
    public function component(): string
    {
        return match ($this) {
            self::Memory => 'gameMemory',
            self::GoodChoice => 'gameGoodChoice',
            self::Order => 'gameOrder',
            self::Hijaiyah, self::Prophets, self::QuickQuiz => 'gameQuiz',
            self::WordSearch => 'gameWordSearch',
        };
    }
}
