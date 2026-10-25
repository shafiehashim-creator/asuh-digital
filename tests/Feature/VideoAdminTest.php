<?php

use App\Enums\AgeGroup;
use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Enums\VideoProvider;
use App\Exceptions\BookNotApproved;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Models\Book;
use App\Models\User;
use App\Models\Video;
use App\Support\VideoId;
use Livewire\Livewire;

it('reads YouTube links and ids in their usual forms', function (string $input) {
    expect(VideoId::normalise(VideoProvider::YouTube, $input))->toBe('dQw4w9WgXcQ');
})->with([
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'https://youtu.be/dQw4w9WgXcQ?si=abc',
    'https://www.youtube.com/embed/dQw4w9WgXcQ',
    'https://youtube.com/shorts/dQw4w9WgXcQ',
    'https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ',
    ' dQw4w9WgXcQ ',
]);

it('lets editors add a video linked to a book, from a pasted YouTube link', function () {
    $book = Book::factory()->published()->create(['title' => 'Kisah Semut']);
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    $this->get('/admin/video')->assertOk();

    Livewire::test(CreateVideo::class)
        ->fillForm([
            'title' => 'Kisah Semut: Animasi',
            'book_id' => $book->id,
            'age_group' => AgeGroup::SixToNine->value,
            'provider' => VideoProvider::YouTube->value,
            'provider_video_id' => 'https://youtu.be/dQw4w9WgXcQ',
            'duration_seconds' => '4:30',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $video = Video::query()->sole();
    expect($video->provider_video_id)->toBe('dQw4w9WgXcQ')
        ->and($video->duration_seconds)->toBe(270)
        ->and($video->book_id)->toBe($book->id)
        ->and($video->status)->toBe(BookStatus::Draft)
        ->and($video->slug)->toBe('kisah-semut-animasi')
        ->and($book->videos()->count())->toBe(1);
});

it('allows videos without a book and rejects bad ids', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateVideo::class)
        ->fillForm(['title' => 'Doa Makan', 'age_group' => '6-9', 'provider' => 'youtube', 'provider_video_id' => 'bukan-pautan'])
        ->call('create')
        ->assertHasFormErrors(['provider_video_id']);

    Livewire::test(CreateVideo::class)
        ->fillForm(['title' => 'Doa Makan', 'age_group' => '6-9', 'provider' => 'bunny', 'provider_video_id' => '3f2a1b4c-1111-2222-3333-444455556666'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Video::query()->sole()->book_id)->toBeNull();
});

it('takes a video through review and publishing', function () {
    $video = Video::factory()->create();
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    Livewire::test(EditVideo::class, ['record' => $video->getRouteKey()])
        ->callAction('submitForReview')
        ->callAction('review', data: ['decision' => 'approve'])
        ->callAction('publish', data: ['published_at' => now()->subMinute()->toDateTimeString()]);

    $video->refresh();
    expect($video->status)->toBe(BookStatus::Published)
        ->and($video->isPublished())->toBeTrue()
        ->and($video->latestReview->status)->toBe(ReviewStatus::Approved)
        ->and($video->latestReview->book_id)->toBeNull();
});

it('never publishes a video without a passed review', function () {
    $video = Video::factory()->create();

    expect(fn () => $video->update(['status' => BookStatus::Published]))->toThrow(BookNotApproved::class);
});

it('keeps the provider id out of serialised data', function () {
    expect(Video::factory()->create()->toArray())->not->toHaveKey('provider_video_id');
});

it('keeps parents out of the video admin', function () {
    $this->actingAs(User::factory()->create())->get('/admin/video')->assertKeptOut();
});
