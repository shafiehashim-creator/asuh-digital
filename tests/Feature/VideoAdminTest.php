<?php

use App\Enums\AgeGroup;
use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Enums\VideoProvider;
use App\Exceptions\BookNotApproved;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Filament\Resources\Videos\Pages\ListVideos;
use App\Models\Book;
use App\Models\Category;
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

it('shows the saved YouTube link when editing, so it need not be pasted again', function () {
    $this->actingAs(User::factory()->admin()->create());
    $video = Video::factory()->create(['provider' => VideoProvider::YouTube, 'provider_video_id' => 'dQw4w9WgXcQ', 'title' => 'Lama']);

    Livewire::test(EditVideo::class, ['record' => $video->slug])
        ->assertSchemaStateSet(['provider_video_id' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])
        ->fillForm(['title' => 'Baharu'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($video->fresh())->title->toBe('Baharu')->provider_video_id->toBe('dQw4w9WgXcQ');
});

it('switches a video to free straight from the list', function () {
    $this->actingAs(User::factory()->admin()->create());
    $video = Video::factory()->create();

    Livewire::test(ListVideos::class)
        ->call('updateTableColumnState', 'is_free_sample', (string) $video->getKey(), true);

    expect($video->fresh()->is_free_sample)->toBeTrue();
});

it('moves several videos through review and publishing at once, skipping those that do not fit', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $drafts = Video::factory()->count(2)->create();
    $published = Video::factory()->published()->create(['title' => 'Sudah Terbit']);

    Livewire::test(ListVideos::class)
        ->callTableBulkAction('submit', [...$drafts, $published])
        ->callTableBulkAction('approve', $drafts)
        ->callTableBulkAction('publish', [...$drafts, $published], ['published_at' => now()->toDateTimeString()])
        ->assertNotified();

    foreach ($drafts as $video) {
        expect($video->fresh())->status->toBe(BookStatus::Published)
            ->and($video->fresh()->latestReview->status)->toBe(ReviewStatus::Approved)
            ->and($video->fresh()->latestReview->reviewer_id)->toBe($admin->id);
    }

    Livewire::test(ListVideos::class)->callTableBulkAction('unpublish', [...$drafts]);
    expect($drafts->map(fn ($v) => $v->fresh()->status)->unique()->all())->toBe([BookStatus::Draft]);
});

it('never publishes a video in bulk without a passed review', function () {
    $this->actingAs(User::factory()->admin()->create());
    $video = Video::factory()->create();

    Livewire::test(ListVideos::class)
        ->callTableBulkAction('publish', [$video], ['published_at' => now()->toDateTimeString()]);

    expect($video->fresh()->status)->toBe(BookStatus::Draft);
});

it('marks free, adds categories and sets the age for several videos at once', function () {
    $this->actingAs(User::factory()->admin()->create());
    $videos = Video::factory()->count(2)->create(['age_group' => AgeGroup::SixToNine]);
    $category = Category::query()->first();

    Livewire::test(ListVideos::class)
        ->callTableBulkAction('makeFree', $videos)
        ->callTableBulkAction('categories', $videos, ['categories' => [$category->id]])
        ->callTableBulkAction('age', $videos, ['age_group' => AgeGroup::TenToThirteen->value]);

    foreach ($videos as $video) {
        $video = $video->fresh();
        expect($video->is_free_sample)->toBeTrue()
            ->and($video->age_group)->toBe(AgeGroup::TenToThirteen)
            ->and($video->categories->pluck('id')->all())->toBe([$category->id]);
    }
});
