<?php

use App\Enums\AgeGroup;
use App\Enums\BookEdition;
use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Exceptions\BookNotApproved;
use App\Models\Book;
use App\Models\Category;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Pass;
use App\Models\SyariahReview;
use App\Models\User;

it('seeds the seven categories from CLAUDE.md', function () {
    expect(Category::query()->orderBy('sort_order')->pluck('name')->all())->toBe([
        'Komik', 'Kisah nabi', 'Akhlak', 'Doa harian', 'Cerita bergambar', 'Novel kanak-kanak', 'Sirah',
    ]);
});

it('gives each book a unique slug from its title', function () {
    $a = Book::factory()->create(['title' => 'Anis dan Anas']);
    $b = Book::factory()->create(['title' => 'Anis dan Anas']);

    expect($a->slug)->toBe('anis-dan-anas')->and($b->slug)->toBe('anis-dan-anas-2');
});

it('refuses to publish a book without a passed syariah review', function (?ReviewStatus $review) {
    $book = Book::factory()->create();

    if ($review) {
        SyariahReview::query()->create(['book_id' => $book->id, 'status' => $review]);
    }

    expect(fn () => $book->update(['status' => BookStatus::Published]))->toThrow(BookNotApproved::class)
        ->and($book->fresh()->status)->toBe(BookStatus::Draft);
})->with([
    'no review' => [null],
    'pending' => [ReviewStatus::Pending],
    'rejected' => [ReviewStatus::Rejected],
]);

it('uses the latest review: a later rejection blocks publishing again', function () {
    $book = Book::factory()->create();
    SyariahReview::query()->create(['book_id' => $book->id, 'status' => ReviewStatus::Approved]);
    SyariahReview::query()->create(['book_id' => $book->id, 'status' => ReviewStatus::Rejected]);

    expect(fn () => $book->update(['status' => BookStatus::Published]))->toThrow(BookNotApproved::class);
});

it('cannot be created already published', function () {
    expect(fn () => Book::factory()->create(['status' => BookStatus::Published]))->toThrow(BookNotApproved::class);
});

it('publishes after approval and stamps the publish date', function () {
    $book = Book::factory()->published()->create();

    expect($book->fresh()->status)->toBe(BookStatus::Published)
        ->and($book->fresh()->published_at)->not->toBeNull()
        ->and(Book::query()->published()->pluck('id')->all())->toBe([$book->id]);
});

it('keeps books with a future publish date out of the published scope', function () {
    $book = Book::factory()->published()->create();
    $book->update(['published_at' => now()->addWeek()]);

    expect(Book::query()->published()->count())->toBe(0);
});

it('filters books for an age group, including books for both ages', function () {
    $young = Book::factory()->create(['age_group' => AgeGroup::SixToNine]);
    $older = Book::factory()->create(['age_group' => AgeGroup::TenToThirteen]);
    $both = Book::factory()->create(['age_group' => AgeGroup::Both]);

    expect(Book::query()->forAge(AgeGroup::SixToNine)->pluck('id')->sort()->values()->all())->toBe([$young->id, $both->id])
        ->and(Book::query()->forAge(AgeGroup::TenToThirteen)->pluck('id')->sort()->values()->all())->toBe([$older->id, $both->id]);
});

it('reads the enhanced edition when the book has one', function () {
    $book = Book::factory()->withFile(BookEdition::Original, 'a.pdf')->withFile(BookEdition::Enhanced, 'b.pdf')->create();

    expect($book->readableFile()->path)->toBe('a.pdf');

    $book->update(['is_enhanced_edition' => true]);

    expect($book->fresh()->readableFile()->path)->toBe('b.pdf');
});

// --- Who may read ------------------------------------------------------------

function activePassFor(User $user): void
{
    $order = Order::factory()->paid()->create(['user_id' => $user->id]);
    Pass::query()->create([
        'user_id' => $user->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
    ]);
}

it('lets a parent with an active pass read published books only', function () {
    $parent = User::factory()->create();
    activePassFor($parent);

    expect($parent->canReadBook(Book::factory()->published()->create()))->toBeTrue()
        ->and($parent->canReadBook(Book::factory()->create()))->toBeFalse();
});

it('keeps parents without a pass out, and lets an entitlement open one book', function () {
    $parent = User::factory()->create();
    $book = Book::factory()->published()->create();
    $other = Book::factory()->published()->create();

    expect($parent->canReadBook($book))->toBeFalse();

    Entitlement::query()->create(['user_id' => $parent->id, 'book_id' => $book->id]);

    expect($parent->canReadBook($book))->toBeTrue()
        ->and($parent->canReadBook($other))->toBeFalse();
});

it('does not open books for an expired pass or one still waiting for the launch date', function () {
    $parent = User::factory()->create();
    $order = Order::factory()->paid()->create(['user_id' => $parent->id]);
    $pass = Pass::query()->create(['user_id' => $parent->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id]);
    $book = Book::factory()->published()->create();

    expect($parent->canReadBook($book))->toBeFalse();

    $pass->update(['starts_at' => now()->subYears(2), 'ends_at' => now()->subYear()]);

    expect($parent->canReadBook($book))->toBeFalse();
});

it('lets staff preview unpublished books for review', function (Role $role) {
    expect(User::factory()->role($role)->create()->canReadBook(Book::factory()->create()))->toBeTrue();
})->with([Role::Admin, Role::Editor]);
