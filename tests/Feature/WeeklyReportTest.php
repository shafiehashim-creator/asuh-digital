<?php

use App\Enums\Badge;
use App\Mail\WeeklyReadingReport;
use App\Models\Book;
use App\Models\Child;
use App\Models\ChildBadge;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReadingSession;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Setting::set('catalog_open', true);
    Mail::fake();
    // Monday 2 Nov 2026, 8 am: the report covers Mon 26 Oct to Sun 1 Nov.
    $this->travelTo('2026-11-02 08:00:00');
});

function readOn(Child $child, string $day, int $minutes, ?Book $book = null): Book
{
    $book ??= Book::factory()->published()->create();
    ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => $book->id, 'read_on' => $day, 'minutes' => $minutes]);

    return $book;
}

it('sends each parent one report on last week\'s reading', function () {
    $child = Child::factory()->create(['nickname' => 'Aisyah']);
    $book = Book::factory()->published()->create(['title' => 'Kisah Semut']);
    readOn($child, '2026-10-26', 12, $book);
    readOn($child, '2026-10-28', 8, $book);
    readOn($child, '2026-10-20', 30); // the week before: not counted

    $quiz = Quiz::query()->create(['book_id' => $book->id]);
    QuizAttempt::query()->create(['child_id' => $child->id, 'quiz_id' => $quiz->id, 'score' => 5, 'total' => 5, 'passed' => true, 'created_at' => '2026-10-28 10:00:00']);
    ChildBadge::query()->create(['child_id' => $child->id, 'badge' => Badge::FirstQuiz, 'awarded_at' => '2026-10-28 10:00:00']);

    $this->artisan('asuh:weekly-reports')->assertSuccessful();

    Mail::assertQueued(WeeklyReadingReport::class, function (WeeklyReadingReport $mail) use ($child) {
        $html = $mail->render();

        return $mail->hasTo($child->user->email)
            && str_contains($html, 'Aisyah')
            && str_contains($html, '20 minit')
            && str_contains($html, '2 hari')
            && str_contains($html, 'Kisah Semut')
            && str_contains($html, 'Kuiz Pertama')
            && str_contains($html, '26 Okt hingga 1 Nov 2026');
    });

    // Running again the same week sends nothing more.
    $this->artisan('asuh:weekly-reports');
    Mail::assertQueuedCount(1);
});

it('leaves out children and parents with no reading last week', function () {
    $reader = Child::factory()->create(['nickname' => 'Along']);
    Child::factory()->for($reader->user)->create(['nickname' => 'Angah']);
    readOn($reader, '2026-10-30', 6);
    Child::factory()->create(); // another family, nothing read

    $this->artisan('asuh:weekly-reports');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(WeeklyReadingReport::class, fn ($mail) => str_contains($mail->render(), 'Along') && ! str_contains($mail->render(), 'Angah'));
});

it('respects the parent\'s choice and sends nothing while the catalogue is closed', function () {
    $child = Child::factory()->create();
    readOn($child, '2026-10-30', 6);
    $child->user->forceFill(['weekly_report' => false])->save();

    $this->artisan('asuh:weekly-reports');
    Mail::assertNothingQueued();

    $child->user->forceFill(['weekly_report' => true])->save();
    Setting::set('catalog_open', false);
    $this->artisan('asuh:weekly-reports');
    Mail::assertNothingQueued();
});

it('stops the report from the signed link in the e-mail', function () {
    $parent = User::factory()->create();

    $this->get(route('weekly-report.unsubscribe', ['user' => $parent->id]))->assertForbidden();

    $this->get(URL::signedRoute('weekly-report.unsubscribe', ['user' => $parent->id]))
        ->assertOk()->assertSee('Laporan mingguan dihentikan');
    expect($parent->refresh()->weekly_report)->toBeFalse();
});

it('switches the report on and off from the account page', function () {
    $parent = User::factory()->create();
    $this->actingAs($parent);

    $this->get('/akaun')->assertSee('Laporan bacaan mingguan');

    $this->put('/akaun/laporan-mingguan', ['weekly_report' => '0'])->assertRedirect('/akaun');
    expect($parent->refresh()->weekly_report)->toBeFalse();

    $this->put('/akaun/laporan-mingguan', ['weekly_report' => '1']);
    expect($parent->refresh()->weekly_report)->toBeTrue();
});

it('is scheduled for Monday morning', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'asuh:weekly-reports'));

    expect($event?->expression)->toBe('0 8 * * 1');
});
