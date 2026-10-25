<?php

use App\Enums\AgeGroup;
use App\Enums\LicenseKind;
use App\Enums\Role;
use App\Mail\LicenseEndingSoon;
use App\Models\Book;
use App\Models\Child;
use App\Models\ChildBadge;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\License;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReadingSession;
use App\Models\User;
use App\Services\ClassReport;
use App\Services\DashboardStats;
use Illuminate\Support\Facades\Mail;

function staffOf(Institution $institution, Role $role = Role::InstitutionAdmin, string $email = 'gb@tadika.my'): User
{
    $user = User::factory()->create(['email' => $email]);
    $user->forceFill(['role' => $role, 'institution_id' => $institution->id])->save();

    return $user;
}

function pupilIn(Classroom $classroom, string $name): Child
{
    $pupil = new Child(['nickname' => $name, 'age_group' => AgeGroup::SixToNine, 'avatar' => 'kucing']);
    $pupil->classroom_id = $classroom->id;
    $pupil->save();

    return $pupil;
}

it('shows each pupil\'s reading progress to the teacher', function () {
    $this->travelTo('2026-10-28 10:00:00'); // Wednesday
    $institution = Institution::factory()->create();
    License::factory()->for($institution)->create();
    $teacher = staffOf($institution, Role::Teacher);
    $classroom = Classroom::factory()->for($institution)->create(['teacher_id' => $teacher->id]);
    $hana = pupilIn($classroom, 'Hana');
    pupilIn($classroom, 'Iman');
    [$a, $b] = Book::factory()->count(2)->published()->create();

    ReadingSession::query()->create(['child_id' => $hana->id, 'book_id' => $a->id, 'read_on' => '2026-10-20', 'minutes' => 10]); // last week
    ReadingSession::query()->create(['child_id' => $hana->id, 'book_id' => $a->id, 'read_on' => '2026-10-26', 'minutes' => 7]);
    ReadingSession::query()->create(['child_id' => $hana->id, 'book_id' => $b->id, 'read_on' => '2026-10-27', 'minutes' => 5]);
    $quiz = Quiz::query()->create(['book_id' => $a->id]);
    QuizAttempt::query()->create(['child_id' => $hana->id, 'quiz_id' => $quiz->id, 'score' => 3, 'total' => 3, 'passed' => true]);
    QuizAttempt::query()->create(['child_id' => $hana->id, 'quiz_id' => $quiz->id, 'score' => 3, 'total' => 3, 'passed' => true]);
    ChildBadge::query()->create(['child_id' => $hana->id, 'badge' => 'kuiz-pertama', 'awarded_at' => now()]);

    $report = app(ClassReport::class)->forClassroom($classroom);
    $row = $report->first();
    expect($row['pupil']->nickname)->toBe('Hana')
        ->and($row['week_minutes'])->toBe(12)
        ->and($row['total_minutes'])->toBe(22)
        ->and($row['books'])->toBe(2)
        ->and($row['quizzes'])->toBe(1)
        ->and($row['badges'])->toBe(1)
        ->and($row['last_read']->toDateString())->toBe('2026-10-27');

    $this->actingAs($teacher)->get("/guru/kelas/{$classroom->id}")->assertOk()
        ->assertSee('Kemajuan bacaan')
        ->assertSeeInOrder(['Hana', '12', '22'])
        ->assertSee('Belum membaca');
});

it('warns the teacher when the licence ends within 30 days', function () {
    $institution = Institution::factory()->create();
    $license = License::factory()->for($institution)->create(['ends_on' => today()->addDays(40)]);
    $teacher = staffOf($institution, Role::Teacher);

    $this->actingAs($teacher)->get('/guru')->assertDontSee('Lesen akan tamat');

    $license->update(['ends_on' => today()->addDays(10)]);
    $this->actingAs($teacher)->get('/guru')->assertSee('Lesen akan tamat dalam 10 hari');
});

it('e-mails institution administrators 30 and 7 days before the licence ends', function () {
    Mail::fake();
    $institution = Institution::factory()->create();
    $license = License::factory()->for($institution)->create(['ends_on' => today()->addDays(30)]);
    $admin = staffOf($institution);
    staffOf($institution, Role::Teacher, 'guru@tadika.my');

    $this->artisan('asuh:license-reminders')->assertSuccessful();
    Mail::assertQueued(LicenseEndingSoon::class, fn ($mail) => $mail->hasTo($admin->email) && ! $mail->hasTo('guru@tadika.my'));

    $this->travel(1)->days();
    $this->artisan('asuh:license-reminders');
    Mail::assertQueuedCount(1);

    $this->travel(22)->days(); // 7 days before the end
    $this->artisan('asuh:license-reminders');
    Mail::assertQueuedCount(2);
});

it('sends no reminder once the licence is renewed', function () {
    Mail::fake();
    $institution = Institution::factory()->create();
    License::factory()->for($institution)->create(['ends_on' => today()->addDays(7)]);
    License::factory()->for($institution)->create(['starts_on' => today()->addDays(8), 'ends_on' => today()->addYear()]);
    staffOf($institution);

    $this->artisan('asuh:license-reminders');

    Mail::assertNothingQueued();
});

it('counts licensed institutions on the admin dashboard', function () {
    License::factory()->create(['kind' => LicenseKind::Paid]);
    License::factory()->create(['kind' => LicenseKind::Pilot, 'ends_on' => today()->addDays(5)]);
    License::factory()->create(['starts_on' => today()->subYears(2), 'ends_on' => today()->subYear()]);

    expect(app(DashboardStats::class)->licenses())->toBe(['active' => 2, 'paid' => 1, 'expiring' => 1]);

    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
});

it('links teachers to the teacher portal from the menu', function () {
    $teacher = staffOf(Institution::factory()->create(), Role::Teacher);

    $this->actingAs($teacher)->get('/guru')->assertSee('Portal guru')->assertDontSee('Akaun saya');
    $this->actingAs(User::factory()->create())->get('/akaun')->assertSee('Akaun saya');
});
