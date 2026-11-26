<?php

namespace Database\Factories;

use App\Enums\AgeGroup;
use App\Enums\QuestionType;
use App\Models\QuizGame;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuizGame> */
class QuizGameFactory extends Factory
{
    protected $model = QuizGame::class;

    public function definition(): array
    {
        return [
            'title' => 'Kuiz '.fake()->unique()->words(2, true),
            'age_group' => AgeGroup::SixToNine,
            'seconds_per_question' => 20,
        ];
    }

    public function published(): static
    {
        return $this->state(['published_at' => now()->subMinute()]);
    }

    /** One question of each kind. */
    public function withQuestions(): static
    {
        return $this->afterCreating(function (QuizGame $quiz) {
            $quiz->questions()->createMany([
                ['sort' => 1, 'type' => QuestionType::Choice, 'prompt' => 'Berapa rakaat solat Subuh?', 'options' => [['t' => '2', 'ok' => true], ['t' => '3', 'ok' => false], ['t' => '4', 'ok' => false]], 'explanation' => 'Subuh 2 rakaat.'],
                ['sort' => 2, 'type' => QuestionType::TrueFalse, 'prompt' => 'Bulan puasa ialah Ramadan.', 'answer' => [true]],
                ['sort' => 3, 'type' => QuestionType::Order, 'prompt' => 'Susun tertib wuduk', 'options' => [['t' => 'Niat'], ['t' => 'Basuh muka'], ['t' => 'Basuh tangan']]],
                ['sort' => 4, 'type' => QuestionType::Fill, 'prompt' => 'Kitab suci umat Islam ialah ...', 'answer' => ['Al-Quran', 'Quran']],
            ]);
        });
    }
}
