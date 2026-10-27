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
    case Math = 'matematik';
    case Science = 'sains';
    case Pattern = 'teka-corak';
    case Sliding = 'puzzle-gelongsor';
    case Sudoku = 'sudoku-emoji';

    /** Levels a child opens one by one with three stars. */
    public const LEVELS = [1 => 'Mudah', 2 => 'Sederhana', 3 => 'Sukar'];

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
            self::Math => 'Matematik Pantas',
            self::Science => 'Sains Ciptaan Allah',
            self::Pattern => 'Teka Corak',
            self::Sliding => 'Puzzle Gelongsor',
            self::Sudoku => 'Sudoku Emoji',
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
            self::Math => '➕',
            self::Science => '🔬',
            self::Pattern => '🧩',
            self::Sliding => '🖼️',
            self::Sudoku => '🔲',
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
            self::Math => 'Kira dengan cepat dan tepat.',
            self::Science => 'Kenali alam ciptaan Allah.',
            self::Pattern => 'Cari coraknya, teka yang seterusnya.',
            self::Sliding => 'Gelongsorkan kepingan hingga gambar lengkap.',
            self::Sudoku => 'Setiap baris, lajur dan kotak ada semua emoji.',
        };
    }

    /** Whether the admin writes its content (letters, sums, patterns and puzzles are made in code). */
    public function editable(): bool
    {
        return in_array($this, [self::Memory, self::GoodChoice, self::Order, self::WordSearch, self::Prophets, self::QuickQuiz, self::Science], true);
    }

    /** The Alpine component that plays it (resources/js/games.js). */
    public function component(): string
    {
        return match ($this) {
            self::Memory => 'gameMemory',
            self::GoodChoice => 'gameGoodChoice',
            self::Order => 'gameOrder',
            self::Hijaiyah, self::Prophets, self::QuickQuiz, self::Math, self::Science, self::Pattern => 'gameQuiz',
            self::WordSearch => 'gameWordSearch',
            self::Sliding => 'gameSliding',
            self::Sudoku => 'gameSudoku',
        };
    }
}
