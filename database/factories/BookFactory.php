<?php

namespace Database\Factories;

use App\Enums\AgeGroup;
use App\Enums\BookEdition;
use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\SyariahReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Kisah '.fake()->unique()->words(2, true),
            'author' => fake()->name(),
            'synopsis' => fake()->paragraph(),
            'age_group' => fake()->randomElement([AgeGroup::SixToNine, AgeGroup::TenToThirteen]),
            'category_id' => fn () => Category::query()->inRandomOrder()->value('id'),
            'status' => BookStatus::Draft,
        ];
    }

    /**
     * Approved by syariah review and published (the only legal way to publish).
     */
    public function published(): static
    {
        return $this->afterCreating(function (Book $book) {
            SyariahReview::query()->create(['book_id' => $book->id, 'status' => ReviewStatus::Approved, 'reviewed_at' => now()]);
            $book->update(['status' => BookStatus::Published, 'published_at' => now()->subMinute()]);
        });
    }

    public function withFile(BookEdition $edition = BookEdition::Original, string $path = 'books/contoh.pdf'): static
    {
        return $this->afterCreating(fn (Book $book) => $book->files()->create([
            'edition' => $edition,
            'path' => $path,
            'page_count' => 12,
        ]));
    }
}
