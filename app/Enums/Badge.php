<?php

namespace App\Enums;

/**
 * The badge set (CLAUDE.md: earned from quizzes answered correctly and from
 * reading on many days, never from pages turned). See BadgeAwarder. The
 * monthly top reader badge is given by ASUH (TopReaders::announce).
 */
enum Badge: string
{
    case FirstQuiz = 'kuiz-pertama';
    case QuizExpert = 'pakar-kuiz';
    case KnowledgeStar = 'bintang-ilmu';
    case KeenReader = 'pembaca-rajin';
    case ReadingWeek = 'seminggu-membaca';
    case SteadyReader = 'pembaca-istiqamah';
    case TopReader = 'pembaca-terbaik';

    public function label(): string
    {
        return match ($this) {
            self::FirstQuiz => 'Kuiz Pertama',
            self::QuizExpert => 'Pakar Kuiz',
            self::KnowledgeStar => 'Bintang Ilmu',
            self::KeenReader => 'Pembaca Rajin',
            self::ReadingWeek => 'Seminggu Membaca',
            self::SteadyReader => 'Pembaca Istiqamah',
            self::TopReader => 'Pembaca Terbaik ASUH',
        };
    }

    /** How to earn it, as shown to the child. */
    public function description(): string
    {
        return match ($this) {
            self::FirstQuiz => 'Lulus kuiz pertama.',
            self::QuizExpert => 'Lulus kuiz untuk 5 buku.',
            self::KnowledgeStar => 'Jawab semua soalan betul dalam kuiz 10 buku.',
            self::KeenReader => 'Membaca 3 hari berturut-turut.',
            self::ReadingWeek => 'Membaca 7 hari berturut-turut.',
            self::SteadyReader => 'Membaca 20 hari dalam sebulan.',
            self::TopReader => 'Antara 5 pembaca terbaik ASUH dalam sebulan.',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::FirstQuiz => '🎯',
            self::QuizExpert => '🧠',
            self::KnowledgeStar => '🌟',
            self::KeenReader => '📖',
            self::ReadingWeek => '🗓️',
            self::SteadyReader => '🏆',
            self::TopReader => '👑',
        };
    }
}
