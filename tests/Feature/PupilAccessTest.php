<?php

use App\Enums\AgeGroup;
use App\Enums\Badge;
use App\Models\Book;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\License;
use App\Models\Quiz;
use App\Models\ReadingSession;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Setting::set('catalog_open', true);
    Storage::fake('local');
    Storage::disk('local')->put('books/contoh.pdf', '%PDF-1.4 ujian');
});

function classWithPupil(string $code = 'AB7K2Q', bool $licensed = true): Child
{
    $institution = Institution::factory()->create(['name' => 'Tadika Seri Ilmu']);
    if ($licensed) {
        License::factory()->for($institution)->create();
    }
    $classroom = Classroom::factory()->for($institution)->create(['code' => $code, 'name' => '2 Bestari', 'age_group' => AgeGroup::SixToNine]);
    $pupil = new Child(['nickname' => 'Hana', 'age_group' => AgeGroup::SixToNine, 'avatar' => 'kucing']);
    $pupil->classroom_id = $classroom->id;
    $pupil->save();

    return $pupil;
}

function joinAs(Child $pupil): void
{
    test()->post('/kelas', ['code' => $pupil->classroom->code])->assertRedirect('/kelas/pilih');
    test()->post("/kelas/pilih/{$pupil->id}")->assertRedirect('/mod-anak');
}

it('joins a class with the code and the pupil\'s own name, without an account', function () {
    $pupil = classWithPupil();
    Book::factory()->published()->create(['title' => 'Buku Adik', 'age_group' => AgeGroup::SixToNine]);
    Book::factory()->published()->create(['title' => 'Buku Abang', 'age_group' => AgeGroup::TenToThirteen]);

    $this->get('/kelas')->assertOk()->assertSee('Jom masuk kelas!');
    $this->post('/kelas', ['code' => 'ab7-k2q'])->assertRedirect('/kelas/pilih');
    $this->get('/kelas/pilih')->assertOk()->assertSee('2 Bestari')->assertSee('Hana');
    $this->post("/kelas/pilih/{$pupil->id}")->assertRedirect('/mod-anak');

    $this->assertGuest();
    $this->get('/mod-anak')->assertOk()
        ->assertSee('Hai, Hana!')
        ->assertSee('Buku Adik')
        ->assertDontSee('Buku Abang')
        ->assertSee('Tukar murid')
        ->assertDontSee('Keluar Mod Anak');
});

it('rejects unknown codes', function () {
    $this->post('/kelas', ['code' => 'ZZZZZZ'])->assertSessionHasErrors('code');
    $this->get('/kelas/pilih')->assertRedirect('/kelas');
});

it('only lets a pupil be picked from the class whose code was entered', function () {
    $pupil = classWithPupil('AB7K2Q');
    $other = classWithPupil('CD8M3R');

    $this->post('/kelas', ['code' => 'AB7K2Q']);
    $this->post("/kelas/pilih/{$other->id}")->assertNotFound();
    // A parent's own child is never a pupil.
    $this->post('/kelas/pilih/'.Child::factory()->create()->id)->assertNotFound();
});

it('keeps pupils out without an active licence', function () {
    $pupil = classWithPupil(licensed: false);

    $this->post('/kelas', ['code' => 'AB7K2Q']);
    $this->get('/kelas/pilih')->assertSee('belum boleh membaca');
    $this->post("/kelas/pilih/{$pupil->id}")->assertRedirect('/kelas/pilih');
    $this->get('/mod-anak')->assertRedirect('/login');
});

it('sends pupils back to the code page when the licence ends', function () {
    $pupil = classWithPupil();
    joinAs($pupil);

    $pupil->classroom->institution->licenses()->update(['ends_on' => today()->subDay()]);

    $this->get('/mod-anak')->assertRedirect('/kelas');
    $this->get('/kelas')->assertSee('Akses kelas sudah tamat');
});

it('locks pupils to the shelf and lets them switch names', function () {
    $pupil = classWithPupil();
    joinAs($pupil);

    $this->get('/akaun')->assertRedirect('/mod-anak');
    $this->get('/buku')->assertRedirect('/mod-anak');
    $this->get('/langganan')->assertRedirect('/mod-anak');

    $this->post('/kelas/keluar')->assertRedirect('/kelas/pilih');
    $this->get('/mod-anak')->assertRedirect('/login');
});

it('reads books under the licence with a pupil-bound link and a class watermark', function () {
    $pupil = classWithPupil();
    $book = Book::factory()->published()->withFile()->create(['age_group' => AgeGroup::SixToNine]);
    joinAs($pupil);

    $response = $this->get("/baca/{$book->slug}")->assertOk()
        ->assertSee('data-watermark="Tadika Seri Ilmu · 2 Bestari', false);
    preg_match('/data-src="([^"]+)"/', $response->getContent(), $match);
    $fileUrl = html_entity_decode($match[1]);
    expect($fileUrl)->toContain('p='.$pupil->id);

    $this->get($fileUrl)->assertOk();

    // Not counted among the families reading the book.
    expect($book->readers()->count())->toBe(0);
});

it('refuses pupil links outside that pupil\'s session', function () {
    $pupil = classWithPupil();
    $book = Book::factory()->published()->withFile()->create(['age_group' => AgeGroup::SixToNine]);
    $url = URL::temporarySignedRoute('reader.file', now()->addMinutes(5), ['book' => $book->slug, 'file' => $book->files()->first()->id, 'p' => $pupil->id]);

    // No session at all.
    $this->get($url)->assertRedirect('/login');

    // A different pupil.
    $other = classWithPupil('CD8M3R');
    joinAs($other);
    $this->get($url)->assertForbidden();

    // A parent with a pass cannot use it either.
    $this->post('/kelas/keluar');
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
});

it('keeps books for another age out of the pupil\'s reader', function () {
    $pupil = classWithPupil();
    $book = Book::factory()->published()->withFile()->create(['age_group' => AgeGroup::TenToThirteen]);
    joinAs($pupil);

    $this->get("/baca/{$book->slug}")->assertRedirect('/mod-anak');
});

it('records reading, quizzes and badges for pupils', function () {
    $pupil = classWithPupil();
    $book = Book::factory()->published()->withFile()->create(['age_group' => AgeGroup::SixToNine]);
    $quiz = Quiz::query()->create(['book_id' => $book->id]);
    foreach (range(1, 3) as $i) {
        $quiz->questions()->create(['question' => "S{$i}?", 'options' => ['A', 'B', 'C'], 'correct_index' => 0, 'sort_order' => $i]);
    }
    joinAs($pupil);

    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 12, 'tick' => true])->assertJson(['minutes' => 1]);
    expect(ReadingSession::query()->sole()->child_id)->toBe($pupil->id);

    $answers = $quiz->questions->mapWithKeys(fn ($q) => [$q->id => 0])->all();
    $this->get("/mod-anak/kuiz/{$book->slug}")->assertOk();
    $this->post("/mod-anak/kuiz/{$book->slug}", ['answers' => $answers])->assertRedirect();

    expect($pupil->badges()->pluck('badge')->all())->toBe([Badge::FirstQuiz]);
});

it('leaves a parent\'s Mod Anak unchanged', function () {
    $child = Child::factory()->create();

    $this->actingAs($child->user)->post("/akaun/anak/{$child->id}/mod-anak")->assertRedirect('/mod-anak');
    $this->get('/mod-anak')->assertOk()->assertSee('Keluar')->assertSee(route('child-mode.gate'))->assertDontSee('Tukar murid');
});

it('keeps a family child out of the class pages, so only the parent gate ends Mod Anak', function () {
    $child = Child::factory()->create();
    $this->actingAs($child->user)->post("/akaun/anak/{$child->id}/mod-anak");

    $this->get('/kelas')->assertRedirect('/mod-anak');
    $this->post('/kelas/keluar')->assertRedirect('/mod-anak');
    $this->post('/kelas/tutup')->assertRedirect('/mod-anak');
    $this->get('/akaun')->assertRedirect('/mod-anak');
});

it('lets a class tablet go back to the main site from the class pages', function () {
    $pupil = classWithPupil();

    $this->get('/kelas')->assertOk()->assertSee('Laman utama');
    joinAs($pupil);
    $this->post('/kelas/keluar')->assertRedirect('/kelas/pilih');
    $this->get('/kelas/pilih')->assertOk()->assertSee('Laman utama');

    $this->post('/kelas/tutup')->assertRedirect('/');
    $this->get('/kelas/pilih')->assertRedirect('/kelas');
    $this->get('/')->assertOk();
});
