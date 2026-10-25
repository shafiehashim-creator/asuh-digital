<?php

namespace Database\Factories;

use App\Enums\AgeGroup;
use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Enums\VideoProvider;
use App\Models\SyariahReview;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Animasi '.fake()->unique()->words(2, true),
            'synopsis' => fake()->sentence(),
            'age_group' => AgeGroup::SixToNine,
            'provider' => VideoProvider::YouTube,
            'provider_video_id' => fake()->regexify('[A-Za-z0-9_-]{11}'),
            'duration_seconds' => 240,
        ];
    }

    /** Reviewed and published, ready for subscribers. */
    public function published(): static
    {
        return $this->afterCreating(function (Video $video) {
            SyariahReview::query()->create(['video_id' => $video->id, 'status' => ReviewStatus::Approved, 'reviewed_at' => now()]);
            $video->update(['status' => BookStatus::Published, 'published_at' => now()->subMinute()]);
        });
    }
}
