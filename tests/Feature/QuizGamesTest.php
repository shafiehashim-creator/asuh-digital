<?php

use App\Enums\AgeGroup;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Filament\Resources\QuizGames\Pages\CreateQuizGame;
use App\Filament\Resources\QuizGames\Pages\ListQuizGames;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\License;
use App\Models\Order;
use App\Models\Pass;
use App\Models\QuizGame;
use App\Models\QuizGameAssignment;
use App\Models\QuizGamePlay;
use App\Models\Setting;
use App\Models\User;
use App\Services\ChildMode;
use App\Services\QuizGames;
use Livewire\Livewire;

beforeEach(function () {
    Setting::set('catalog_open', true);
    Setting::set('quiz_games_open', true);
});

function quizFamily(): User
{
    $user = User::factory()->create();
    $order = Order::factory()->paid()->create(['user_id' => $user->id]);
    Pass::query()->create(['user_id' => $user->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);

    return $user;
}

/** @return array{0: Child, 1: Classroom, 2: User} a pupil, their class and its teacher */
function quizClass(string $code = 'KUIZ22'): array
{
    $institution = Institution::factory()->create();
    License::factory()->for($institution)->create();
    $teacher = User::factory()->create();
    $teacher->forceFill(['role' => Role::Teacher, 'institution_id' => $institution->id])->save();
    $classroom = Classroom::factory()->for($institution)->create(['code' => $code, 'teacher_id' => $teacher->id, 'age_group' => AgeGroup::SixToNine]);
    $pupil = new Child(['nickname' => 'Hana', 'age_group' => AgeGroup::SixToNine, 'avatar' => 'kucing']);
    $pupil->classroom_id = $classroom->id;
    $pupil->save();

    return [$pupil, $classroom, $teacher];
}

function playAll($test, QuizGame $quiz, array $answers): array
{
    $start = $test->postJson(route('child-mode.quiz-games.start', $quiz))->assertOk()->json();
    foreach ($start['questions'] as $i => $question) {
        $test->postJson($start['answerUrl'], ['question' => $question['id'], 'answer' => $answers[$i], 'ms' => 2000])->assertOk();
    }

    return $test->postJson($start['finishUrl'])->assertOk()->json();
}

it('checks every kind of answer on the server and scores speed and streaks', function () {
    $quiz = QuizGame::factory()->published()->withQuestions()->create();
    $family = quizFamily();
    $play = app(QuizGames::class)->start($family, $quiz);
    [$choice, $truth, $order, $fill] = $quiz->questions;
    $service = app(QuizGames::class);

    $first = $service->answer($play, $choice, 0, 0);
    expect($first)->toMatchArray(['correct' => true, 'points' => 1000, 'streak' => 1, 'answer' => '2', 'explanation' => 'Subuh 2 rakaat.']);
    // A question counts once, however many times it is sent.
    expect($service->answer($play->fresh(), $choice, 1, 0)['correct'])->toBeTrue();

    $second = $service->answer($play->fresh(), $truth, true, 10000);
    expect($second['points'])->toBe(500 + 250 + 100)->and($second['streak'])->toBe(2);

    expect($service->answer($play->fresh(), $order, ['Niat', 'Basuh tangan', 'Basuh muka'], 1000))->toMatchArray(['correct' => false, 'points' => 0, 'answer' => ['Niat', 'Basuh muka', 'Basuh tangan']]);
    expect($service->answer($play->fresh(), $fill, '  quran! ', 1000)['correct'])->toBeTrue();

    expect($play->fresh())->score->toBe(1000 + 850 + 975)->correct->toBe(3);
});

it('lets a subscribed child play from Mod Anak and keeps answers out of the page', function () {
    $quiz = QuizGame::factory()->published()->withQuestions()->create(['title' => 'Kuiz Solat']);
    QuizGame::factory()->published()->withQuestions()->create(['title' => 'Kuiz Remaja', 'age_group' => AgeGroup::TenToThirteen]);
    $family = quizFamily();
    $child = Child::factory()->for($family)->create(['age_group' => AgeGroup::SixToNine]);
    $this->actingAs($family);
    app(ChildMode::class)->enter($child);

    $this->get(route('child-mode.home'))->assertSee('Kuiz Interaktif')->assertSee('Kuiz Solat')->assertDontSee('Kuiz Remaja');
    $this->get(route('child-mode.quiz-games.show', $quiz))->assertOk()->assertSee('▶ Mula');

    $start = $this->postJson(route('child-mode.quiz-games.start', $quiz))->assertOk()->json();
    expect(json_encode($start['questions']))->not->toContain('"ok"')->not->toContain('Al-Quran');

    $summary = playAll($this, $quiz, [0, true, ['Niat', 'Basuh muka', 'Basuh tangan'], 'Al-Quran']);
    expect($summary)->toMatchArray(['correct' => 4, 'total' => 4])->and($summary['score'])->toBeGreaterThan(3000);
    expect(QuizGamePlay::query()->whereNotNull('finished_at')->sole())->child_id->toBe($child->id);
});

it('keeps quizzes hidden while closed, unpublished, or without a subscription', function () {
    $quiz = QuizGame::factory()->published()->withQuestions()->create();
    $draft = QuizGame::factory()->withQuestions()->create();
    $family = quizFamily();

    $this->actingAs($family)->get(route('child-mode.quiz-games.show', $quiz))->assertOk();
    $this->actingAs($family)->get(route('child-mode.quiz-games.show', $draft))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('child-mode.quiz-games.show', $quiz))->assertNotFound();

    Setting::set('quiz_games_open', false);
    $this->actingAs($family)->get(route('child-mode.quiz-games.show', $quiz))->assertNotFound();
    // Staff can always try them, drafts too.
    $this->actingAs(User::factory()->role(Role::Editor)->create())->get(route('child-mode.quiz-games.show', $draft))->assertOk();
});

it('does not let one player answer for another', function () {
    $quiz = QuizGame::factory()->published()->withQuestions()->create();
    $play = app(QuizGames::class)->start(quizFamily(), $quiz);

    $this->actingAs(quizFamily())
        ->postJson(route('child-mode.quiz-games.answer', $play), ['question' => $quiz->questions->first()->id, 'answer' => 0, 'ms' => 100])
        ->assertNotFound();
});

it('lets teachers give a quiz to their class and see the leaderboard', function () {
    $quiz = QuizGame::factory()->published()->withQuestions()->create(['title' => 'Kuiz Wuduk']);
    [$pupil, $classroom, $teacher] = quizClass();
    $other = new Child(['nickname' => 'Adam', 'age_group' => AgeGroup::SixToNine, 'avatar' => 'kucing']);
    $other->classroom_id = $classroom->id;
    $other->save();

    $this->actingAs($teacher)->get(route('teacher.home'))->assertOk()->assertSee('Kuiz Interaktif');
    $this->actingAs($teacher)->get(route('teacher.quiz-games.index'))->assertOk()->assertSee('Kuiz Wuduk');
    $this->actingAs($teacher)->post(route('teacher.quiz-games.assign', $quiz), ['classroom' => $classroom->id])
        ->assertRedirect();
    $assignment = QuizGameAssignment::query()->sole();
    auth()->logout();

    // The pupil sees it under "Kuiz daripada cikgu" and plays it.
    $this->post('/kelas', ['code' => 'KUIZ22']);
    $this->post("/kelas/pilih/{$pupil->id}");
    $this->get(route('child-mode.home'))->assertSee('Kuiz daripada cikgu')->assertSee('Kuiz Wuduk');
    $summary = playAll($this, $quiz, [0, false, [], 'quran']);
    expect($summary['rank'])->toBe(['place' => 1, 'of' => 1])
        ->and(QuizGamePlay::query()->sole()->assignment_id)->toBe($assignment->id);

    // The teacher looks from their own device, not the class tablet.
    $this->flushSession();
    $this->actingAs($teacher)->get(route('teacher.quiz-games.results', $assignment))->assertOk()
        ->assertSee('Hana')->assertSee('2/4 betul')->assertSee('Belum main (1):')->assertSee('Adam');
    expect($this->actingAs($teacher)->get(route('teacher.quiz-games.export', $assignment))->streamedContent())->toContain('Hana');
});

it('only gives pupils the quizzes their class was given', function () {
    $quiz = QuizGame::factory()->published()->withQuestions()->create();
    [$pupil] = quizClass();
    $this->post('/kelas', ['code' => 'KUIZ22']);
    $this->post("/kelas/pilih/{$pupil->id}");

    $this->get(route('child-mode.quiz-games.show', $quiz))->assertNotFound();
});

it('keeps another school\'s class results private', function () {
    $quiz = QuizGame::factory()->published()->withQuestions()->create();
    [, $classroom] = quizClass();
    $assignment = QuizGameAssignment::query()->create(['quiz_game_id' => $quiz->id, 'classroom_id' => $classroom->id]);
    [, , $otherTeacher] = quizClass('LAIN33');

    $this->actingAs($otherTeacher)->get(route('teacher.quiz-games.results', $assignment))->assertNotFound();
    $this->actingAs($otherTeacher)->post(route('teacher.quiz-games.assign', $quiz), ['classroom' => $classroom->id])->assertSessionHasErrors('classroom');
});

it('lets editors build a quiz with every kind of question, and only admins publish', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    Livewire::test(CreateQuizGame::class)
        ->fillForm([
            'title' => 'Kuiz Akhlak', 'age_group' => '6-9', 'seconds_per_question' => 15,
            'questions' => [
                ['type' => 'pilihan', 'prompt' => 'Apa kita ucap bila bersin?', 'options' => [['t' => 'Alhamdulillah', 'ok' => true], ['t' => 'Hai', 'ok' => false]]],
                ['type' => 'betul_salah', 'prompt' => 'Senyum itu sedekah.', 'tf_answer' => '1'],
                ['type' => 'susun', 'prompt' => 'Susun', 'options' => [['t' => 'Satu'], ['t' => 'Dua'], ['t' => 'Tiga']]],
                ['type' => 'isi', 'prompt' => 'Ucapan salam: Assalamu...', 'fill_answers' => ['alaikum']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quiz = QuizGame::query()->sole();
    expect($quiz->questions->pluck('type')->all())->toBe([QuestionType::Choice, QuestionType::TrueFalse, QuestionType::Order, QuestionType::Fill])
        ->and($quiz->questions[1]->answer)->toBe([true])
        ->and($quiz->questions[3]->answer)->toBe(['alaikum'])
        ->and($quiz->published_at)->toBeNull();

    Livewire::test(ListQuizGames::class)->assertTableActionHidden('publish', $quiz);

    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(ListQuizGames::class)->callTableAction('publish', $quiz);
    expect($quiz->fresh()->isPublished())->toBeTrue();
});

it('needs exactly one right choice', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateQuizGame::class)
        ->fillForm(['title' => 'Kuiz', 'age_group' => '6-9', 'seconds_per_question' => 20, 'questions' => [
            ['type' => 'pilihan', 'prompt' => 'Soalan?', 'options' => [['t' => 'A', 'ok' => false], ['t' => 'B', 'ok' => false]]],
        ]])
        ->call('create')
        ->assertHasFormErrors();
});
