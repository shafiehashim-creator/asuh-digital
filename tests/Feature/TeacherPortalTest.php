<?php

use App\Enums\AgeGroup;
use App\Enums\Role;
use App\Filament\Resources\Children\Pages\ListChildren;
use App\Mail\InstitutionInvitation;
use App\Models\Book;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\License;
use App\Models\ReadingSession;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function institutionMember(Institution $institution, Role $role = Role::Teacher): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => $role, 'institution_id' => $institution->id])->save();

    return $user;
}

function licensedInstitution(int $seats = 30): Institution
{
    $institution = Institution::factory()->create();
    License::factory()->for($institution)->create(['max_students' => $seats]);

    return $institution;
}

// --- Classes ---------------------------------------------------------------------

it('creates a class with a readable six-character code', function () {
    $teacher = institutionMember(licensedInstitution());

    $this->actingAs($teacher)->get('/guru/kelas/baru')->assertOk();
    $this->actingAs($teacher)->post('/guru/kelas', ['name' => '2 Bestari', 'age_group' => '6-9'])->assertRedirect();

    $classroom = Classroom::query()->sole();
    expect($classroom->teacher_id)->toBe($teacher->id)
        ->and($classroom->age_group)->toBe(AgeGroup::SixToNine)
        ->and($classroom->code)->toMatch('/^[ABCDEFGHJKMNPQRTUVWXYZ2346789]{6}$/');

    $this->actingAs($teacher)->get("/guru/kelas/{$classroom->id}")->assertOk()->assertSee($classroom->code)->assertSee('2 Bestari');
});

it('finds a class by code however the child types it', function () {
    $classroom = Classroom::factory()->create(['code' => 'AB7K2Q']);

    expect(Classroom::findByCode('ab7k2q')?->id)->toBe($classroom->id)
        ->and(Classroom::findByCode(' AB7-K2Q ')?->id)->toBe($classroom->id)
        ->and(Classroom::findByCode('AB7K2'))->toBeNull();
});

it('shows teachers their own classes and the administrator every class', function () {
    $institution = licensedInstitution();
    $admin = institutionMember($institution, Role::InstitutionAdmin);
    $teacher = institutionMember($institution);
    $other = institutionMember($institution);
    $mine = Classroom::factory()->for($institution)->create(['teacher_id' => $teacher->id, 'name' => 'Kelas Saya']);
    $theirs = Classroom::factory()->for($institution)->create(['teacher_id' => $other->id, 'name' => 'Kelas Orang Lain']);

    $this->actingAs($teacher)->get('/guru')->assertSee('Kelas Saya')->assertDontSee('Kelas Orang Lain');
    $this->actingAs($teacher)->get("/guru/kelas/{$theirs->id}")->assertNotFound();

    $this->actingAs($admin)->get('/guru')->assertSee('Kelas Saya')->assertSee('Kelas Orang Lain');
    $this->actingAs($admin)->get("/guru/kelas/{$theirs->id}")->assertOk();
});

it('keeps classes of other institutions out of reach', function () {
    $classroom = Classroom::factory()->create();
    $stranger = institutionMember(licensedInstitution(), Role::InstitutionAdmin);

    $this->actingAs($stranger)->get("/guru/kelas/{$classroom->id}")->assertNotFound();
    $this->actingAs($stranger)->delete("/guru/kelas/{$classroom->id}")->assertNotFound();
    $this->actingAs($stranger)->post("/guru/kelas/{$classroom->id}/murid", ['names' => 'Ali'])->assertNotFound();
    expect($classroom->fresh())->not->toBeNull();
});

it('gives a class a new code', function () {
    $teacher = institutionMember(licensedInstitution());
    $classroom = Classroom::factory()->for($teacher->institution)->create(['teacher_id' => $teacher->id, 'code' => 'AAAAAA']);

    $this->actingAs($teacher)->post("/guru/kelas/{$classroom->id}/kod-baharu")->assertRedirect();

    expect($classroom->fresh()->code)->not->toBe('AAAAAA');
});

// --- Pupils ----------------------------------------------------------------------

it('adds pupils one per line, skipping blanks and repeats', function () {
    $teacher = institutionMember(licensedInstitution());
    $classroom = Classroom::factory()->for($teacher->institution)->create(['teacher_id' => $teacher->id, 'age_group' => AgeGroup::TenToThirteen]);
    $existing = new Child(['nickname' => 'Aisyah', 'age_group' => AgeGroup::TenToThirteen, 'avatar' => 'anis']);
    $existing->classroom_id = $classroom->id;
    $existing->save();

    $this->actingAs($teacher)->post("/guru/kelas/{$classroom->id}/murid", ['names' => "Muhammad\n\n  Nur   Iman \nmuhammad\naisyah"])
        ->assertRedirect()->assertSessionHas('added', 2);

    expect($classroom->pupils()->pluck('nickname')->all())->toBe(['Aisyah', 'Muhammad', 'Nur Iman'])
        ->and($classroom->pupils()->where('nickname', 'Nur Iman')->sole()->age_group)->toBe(AgeGroup::TenToThirteen)
        ->and($classroom->pupils()->whereNotNull('user_id')->count())->toBe(0);
});

it('stops at the licence\'s pupil limit across all classes', function () {
    $institution = licensedInstitution(seats: 3);
    $teacher = institutionMember($institution);
    $a = Classroom::factory()->for($institution)->create(['teacher_id' => $teacher->id]);
    $b = Classroom::factory()->for($institution)->create(['teacher_id' => $teacher->id]);

    $this->actingAs($teacher)->post("/guru/kelas/{$a->id}/murid", ['names' => "Ali\nAbu"])->assertSessionHasNoErrors();
    $this->actingAs($teacher)->post("/guru/kelas/{$b->id}/murid", ['names' => "Siti\nSalmah"])->assertSessionHasErrors('names');
    $this->actingAs($teacher)->post("/guru/kelas/{$b->id}/murid", ['names' => 'Siti'])->assertSessionHasNoErrors();

    expect($institution->pupilCount())->toBe(3);
});

it('adds no pupils without an active licence', function () {
    $institution = Institution::factory()->create();
    $teacher = institutionMember($institution);
    $classroom = Classroom::factory()->for($institution)->create(['teacher_id' => $teacher->id]);

    $this->actingAs($teacher)->post("/guru/kelas/{$classroom->id}/murid", ['names' => 'Ali'])->assertSessionHasErrors('names');
});

it('deletes a pupil, or a whole class, with their reading records', function () {
    $teacher = institutionMember(licensedInstitution());
    $classroom = Classroom::factory()->for($teacher->institution)->create(['teacher_id' => $teacher->id]);
    $this->actingAs($teacher)->post("/guru/kelas/{$classroom->id}/murid", ['names' => "Ali\nAbu"]);
    [$ali, $abu] = $classroom->pupils()->get();
    $book = Book::factory()->published()->create();
    ReadingSession::query()->create(['child_id' => $ali->id, 'book_id' => $book->id, 'read_on' => today(), 'minutes' => 5]);

    $this->actingAs($teacher)->delete("/guru/kelas/{$classroom->id}/murid/{$ali->id}")->assertRedirect();
    expect(ReadingSession::query()->count())->toBe(0)->and(Child::query()->count())->toBe(1);

    $this->actingAs($teacher)->delete("/guru/kelas/{$classroom->id}")->assertRedirect('/guru');
    expect(Child::query()->count())->toBe(0)->and(Classroom::query()->count())->toBe(0);
});

it('does not let a parent\'s child be removed through a class', function () {
    $teacher = institutionMember(licensedInstitution());
    $classroom = Classroom::factory()->for($teacher->institution)->create(['teacher_id' => $teacher->id]);
    $child = Child::factory()->create();

    $this->actingAs($teacher)->delete("/guru/kelas/{$classroom->id}/murid/{$child->id}")->assertNotFound();
    expect($child->fresh())->not->toBeNull();
});

// --- Teachers --------------------------------------------------------------------

it('lets the institution administrator invite and remove teachers', function () {
    Mail::fake();
    $institution = licensedInstitution();
    $admin = institutionMember($institution, Role::InstitutionAdmin);
    $teacher = institutionMember($institution);
    $classroom = Classroom::factory()->for($institution)->create(['teacher_id' => $teacher->id]);

    $this->actingAs($admin)->post('/guru/guru', ['name' => 'Cikgu Aminah', 'email' => 'aminah@sekolah.my'])->assertRedirect('/guru');
    Mail::assertQueued(InstitutionInvitation::class, fn ($mail) => $mail->invitation->role === Role::Teacher);
    $this->actingAs($admin)->get('/guru')->assertSee('aminah@sekolah.my')->assertSee('jemputan menunggu');

    $this->actingAs($admin)->delete("/guru/guru/{$teacher->id}")->assertRedirect('/guru');
    expect($teacher->fresh()->institution_id)->toBeNull()
        ->and($teacher->fresh()->role)->toBe(Role::Parent)
        ->and($classroom->fresh()->teacher_id)->toBe($admin->id);
});

it('does not let teachers invite or remove teachers', function () {
    $institution = licensedInstitution();
    $teacher = institutionMember($institution);
    $other = institutionMember($institution);

    $this->actingAs($teacher)->post('/guru/guru', ['name' => 'X', 'email' => 'x@sekolah.my'])->assertForbidden();
    $this->actingAs($teacher)->delete("/guru/guru/{$other->id}")->assertNotFound();
});

it('lists pupils with their class in the admin child list', function () {
    $classroom = Classroom::factory()->create(['name' => 'Kelas Al-Farabi']);
    $pupil = new Child(['nickname' => 'Hana', 'age_group' => AgeGroup::SixToNine, 'avatar' => 'kucing']);
    $pupil->classroom_id = $classroom->id;
    $pupil->save();
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/profil-anak')->assertOk()->assertSee('Kelas Al-Farabi');
    Livewire::test(ListChildren::class)->assertCanSeeTableRecords([$pupil]);
});
