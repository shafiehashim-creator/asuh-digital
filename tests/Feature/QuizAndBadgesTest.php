<?php

use App\Enums\AgeGroup;
use App\Enums\Badge;
use App\Enums\Role;
use App\Filament\Resources\Quizzes\Pages\CreateQuiz;
use App\Models\Book;
use App\Models\Child;
use App\Models\ChildBadge;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReadingSession;
use App\Models\Setting;
use App\Models\User;
use App\Services\BadgeAwarder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Setting::set('catalog_open', true);
    Storage::fake('local');
    Storage::disk('local')->put('books/contoh.pdf', '%PDF-1.4 ujian');
});

function quizChild(): Child
{
    $parent = User::factory()->create();
    $order = Order::factory()->paid()->create(['user_id' => $parent->id]);
    Pass::query()->create([
        'user_id' => $parent->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
    ]);

    return Child::factory()->for($parent)->create(['nickname' => 'Aisyah', 'age_group' => AgeGroup::SixToNine]);
}

/** A book with a quiz of $count questions; the right answer is always option 1 (B). */
function bookWithQuiz(int $count = 5): Book
{
    $book = Book::factory()->published()->withFile()->create(['age_group' => AgeGroup::SixToNine, 'title' => 'Kisah Semut']);
    $quiz = Quiz::query()->create(['book_id' => $book->id]);
    foreach (range(1, $count) as $i) {
        $quiz->questions()->create([
            'question' => "Soalan {$i}?", 'options' => ['Salah', "Betul {$i}", 'Salah juga'], 'correct_index' => 1, 'sort_order' => $i,
        ]);
    }

    return $book;
}

function startChildMode(Child $child): void
{
    test()->actingAs($child->user)->post("/akaun/anak/{$child->id}/mod-anak");
}

function markRead(Child $child, Book $book): void
{
    ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => $book->id, 'read_on' => today(), 'minutes' => 3, 'last_page' => 12]);
}

/** @param list<int> $choices one per question, in order */
function answersFor(Book $book, array $choices): array
{
    return ['answers' => $book->quiz->questions->values()->mapWithKeys(fn ($q, $i) => [$q->id => $choices[$i]])->all()];
}

// --- Taking the quiz -------------------------------------------------------------

it('opens the quiz only after the child has read the book in Mod Anak', function () {
    $child = quizChild();
    $book = bookWithQuiz();
    startChildMode($child);

    $this->get("/mod-anak/kuiz/{$book->slug}")->assertRedirect("/baca/{$book->slug}");

    markRead($child, $book);
    $this->get("/mod-anak/kuiz/{$book->slug}")->assertOk()
        ->assertSee('Kuiz: Kisah Semut')
        ->assertSee('Soalan 1?')
        ->assertSee('Betul 1');
});

it('is closed outside Mod Anak, for unplayable quizzes and for other ages', function () {
    $child = quizChild();
    $book = bookWithQuiz();
    markRead($child, $book);

    $this->actingAs($child->user)->get("/mod-anak/kuiz/{$book->slug}")->assertNotFound();

    startChildMode($child);
    $book->quiz->update(['is_active' => false]);
    $this->get("/mod-anak/kuiz/{$book->slug}")->assertNotFound();

    $short = bookWithQuiz(2);
    markRead($child, $short);
    $this->get("/mod-anak/kuiz/{$short->slug}")->assertNotFound();

    $older = Book::factory()->published()->withFile()->create(['age_group' => AgeGroup::TenToThirteen]);
    Quiz::query()->create(['book_id' => $older->id]);
    $this->get("/mod-anak/kuiz/{$older->slug}")->assertNotFound();
});

it('passes at 80 percent and gives the first quiz badge', function () {
    $child = quizChild();
    $book = bookWithQuiz(5);
    markRead($child, $book);
    startChildMode($child);

    $this->post("/mod-anak/kuiz/{$book->slug}", answersFor($book, [1, 1, 1, 1, 0]))
        ->assertRedirect("/mod-anak/kuiz/{$book->slug}/keputusan");

    $this->get("/mod-anak/kuiz/{$book->slug}/keputusan")->assertOk()
        ->assertSee('Tahniah, Aisyah!')
        ->assertSee('4 daripada 5 betul')
        ->assertSee('Lencana baharu!')
        ->assertSee('Kuiz Pertama');

    $attempt = QuizAttempt::query()->sole();
    expect($attempt->passed)->toBeTrue()
        ->and($child->badges()->pluck('badge')->all())->toBe([Badge::FirstQuiz]);
});

it('fails below 80 percent without revealing the answers', function () {
    $child = quizChild();
    $book = bookWithQuiz(5);
    markRead($child, $book);
    startChildMode($child);

    $this->post("/mod-anak/kuiz/{$book->slug}", answersFor($book, [1, 1, 1, 0, 0]));

    $this->get("/mod-anak/kuiz/{$book->slug}/keputusan")->assertOk()
        ->assertSee('Hampir berjaya!')
        ->assertSee('3 daripada 5 betul')
        ->assertSee('Cuba lagi')
        ->assertDontSee('Betul 4');

    expect(QuizAttempt::query()->sole()->passed)->toBeFalse()
        ->and($child->badges()->count())->toBe(0);
});

it('needs every question answered with a real choice', function () {
    $child = quizChild();
    $book = bookWithQuiz(3);
    markRead($child, $book);
    startChildMode($child);

    $answers = answersFor($book, [1, 1, 7]);
    $this->post("/mod-anak/kuiz/{$book->slug}", $answers)->assertSessionHasErrors();

    $answers = answersFor($book, [1, 1, 1]);
    array_pop($answers['answers']);
    $this->post("/mod-anak/kuiz/{$book->slug}", $answers)->assertSessionHasErrors();

    expect(QuizAttempt::query()->count())->toBe(0);
});

it('offers the quiz at the end of the book in the reader', function () {
    $child = quizChild();
    $book = bookWithQuiz();
    startChildMode($child);

    $this->get("/baca/{$book->slug}")->assertOk()
        ->assertSee('data-quiz-cta', false)
        ->assertSee(route('child-mode.quiz.show', $book), false);
});

// --- Badges ----------------------------------------------------------------------

it('awards quiz badges by distinct books passed and perfect scores', function () {
    $child = quizChild();
    $quizzes = collect(range(1, 10))->map(fn () => Quiz::query()->create(['book_id' => Book::factory()->create()->id]));

    foreach ($quizzes->take(4) as $quiz) {
        QuizAttempt::query()->create(['child_id' => $child->id, 'quiz_id' => $quiz->id, 'score' => 5, 'total' => 5, 'passed' => true]);
    }
    // Passing the same book again does not count twice.
    QuizAttempt::query()->create(['child_id' => $child->id, 'quiz_id' => $quizzes[0]->id, 'score' => 5, 'total' => 5, 'passed' => true]);

    expect(app(BadgeAwarder::class)->check($child))->toBe([Badge::FirstQuiz]);

    foreach ($quizzes->slice(4) as $quiz) {
        QuizAttempt::query()->create(['child_id' => $child->id, 'quiz_id' => $quiz->id, 'score' => 5, 'total' => 5, 'passed' => true]);
    }

    expect(app(BadgeAwarder::class)->check($child))->toBe([Badge::QuizExpert, Badge::KnowledgeStar])
        ->and(app(BadgeAwarder::class)->check($child))->toBe([]);
});

it('awards reading badges for streaks of days with five minutes or more', function () {
    $child = quizChild();
    $book = Book::factory()->published()->create();
    $read = fn (string $day, int $minutes) => ReadingSession::query()->create([
        'child_id' => $child->id, 'book_id' => $book->id, 'read_on' => $day, 'minutes' => $minutes,
    ]);

    $read('2026-10-01', 5);
    $read('2026-10-02', 4); // too short: breaks the run
    $read('2026-10-03', 6);
    $read('2026-10-04', 10);
    expect(app(BadgeAwarder::class)->check($child))->toBe([]);

    $read('2026-10-05', 5);
    expect(app(BadgeAwarder::class)->check($child))->toBe([Badge::KeenReader]);

    foreach (range(6, 9) as $d) {
        $read("2026-10-0{$d}", 5);
    }
    expect(app(BadgeAwarder::class)->check($child))->toBe([Badge::ReadingWeek]);
});

it('adds minutes from several books on the same day', function () {
    $child = quizChild();
    [$a, $b] = Book::factory()->count(2)->published()->create();
    foreach (['2026-10-01', '2026-10-02', '2026-10-03'] as $day) {
        ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => $a->id, 'read_on' => $day, 'minutes' => 3]);
        ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => $b->id, 'read_on' => $day, 'minutes' => 2]);
    }

    expect(app(BadgeAwarder::class)->check($child))->toBe([Badge::KeenReader]);
});

it('awards the steady reader badge for 20 reading days in one month', function () {
    $child = quizChild();
    $book = Book::factory()->published()->create();
    // Every other day: 15 days in October, then 5 more in November.
    foreach (range(1, 29, 2) as $d) {
        ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => $book->id, 'read_on' => sprintf('2026-10-%02d', $d), 'minutes' => 5]);
    }
    foreach (range(1, 9, 2) as $d) {
        ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => $book->id, 'read_on' => sprintf('2026-11-%02d', $d), 'minutes' => 5]);
    }
    expect(app(BadgeAwarder::class)->check($child))->not->toContain(Badge::SteadyReader);

    foreach ([11, 13, 15, 17, 19, 21, 23, 25, 27, 29, 2, 4, 6, 8, 10] as $d) {
        ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => $book->id, 'read_on' => sprintf('2026-11-%02d', $d), 'minutes' => 5]);
    }
    expect(app(BadgeAwarder::class)->check($child))->toContain(Badge::SteadyReader);
});

it('awards reading badges from the reader heartbeat', function () {
    $child = quizChild();
    $book = Book::factory()->published()->withFile()->create(['age_group' => AgeGroup::SixToNine]);
    foreach ([2, 1] as $daysAgo) {
        ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => $book->id, 'read_on' => today()->subDays($daysAgo), 'minutes' => 5]);
    }
    ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => $book->id, 'read_on' => today(), 'minutes' => 4, 'last_tick_at' => now()->subMinute()]);
    startChildMode($child);

    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 3, 'tick' => true])->assertJson(['minutes' => 5]);

    expect($child->badges()->pluck('badge')->all())->toBe([Badge::KeenReader]);
});

it('shows earned and locked badges to the child', function () {
    $child = quizChild();
    ChildBadge::query()->create(['child_id' => $child->id, 'badge' => Badge::FirstQuiz, 'awarded_at' => now()]);
    startChildMode($child);

    $this->get('/mod-anak')->assertSee('Lencana saya')->assertSee('1 / 6');
    $this->get('/mod-anak/lencana')->assertOk()
        ->assertSee('Kuiz Pertama')
        ->assertSee('Diperoleh')
        ->assertSee('Pembaca Istiqamah')
        ->assertSee('Belum diperoleh');
});

it('deletes attempts and badges with the child profile', function () {
    $child = quizChild();
    $book = bookWithQuiz();
    QuizAttempt::query()->create(['child_id' => $child->id, 'quiz_id' => $book->quiz->id, 'score' => 5, 'total' => 5, 'passed' => true]);
    ChildBadge::query()->create(['child_id' => $child->id, 'badge' => Badge::FirstQuiz, 'awarded_at' => now()]);

    $child->delete();

    expect(QuizAttempt::query()->count())->toBe(0)->and(ChildBadge::query()->count())->toBe(0);
});

// --- Admin -----------------------------------------------------------------------

it('lets editors write a quiz for a book', function () {
    $book = Book::factory()->published()->create(['title' => 'Adab Makan']);
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    $this->get('/admin/kuiz')->assertOk();

    // A simple repeater holds each option as ['text' => ...] while editing.
    $options = [['text' => 'Kanan'], ['text' => 'Kiri'], ['text' => 'Kedua-dua']];
    $question = fn (string $text) => ['question' => $text, 'options' => $options, 'correct_index' => 0];

    Livewire::test(CreateQuiz::class)
        ->fillForm([
            'book_id' => $book->id,
            'is_active' => true,
            'questions' => [$question('Makan dengan tangan?'), $question('Minum dengan tangan?'), $question('Salam dengan tangan?')],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quiz = $book->quiz()->with('questions')->first();
    expect($quiz->questions)->toHaveCount(3)
        ->and($quiz->questions->first()->options)->toBe(['Kanan', 'Kiri', 'Kedua-dua'])
        ->and($quiz->isPlayable())->toBeTrue();
});

it('needs three to five questions in a quiz', function () {
    $book = Book::factory()->published()->create();
    $this->actingAs(User::factory()->admin()->create());
    $question = ['question' => 'Soalan?', 'options' => ['A', 'B', 'C'], 'correct_index' => 0];

    Livewire::test(CreateQuiz::class)
        ->fillForm(['book_id' => $book->id, 'questions' => [$question, $question]])
        ->call('create')
        ->assertHasFormErrors(['questions']);
});

it('keeps parents out of the quiz admin', function () {
    $this->actingAs(User::factory()->create())->get('/admin/kuiz')->assertKeptOut();
});
