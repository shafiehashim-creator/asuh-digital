<?php

use App\Enums\AgeGroup;
use App\Enums\ChildAvatar;
use App\Enums\Role;
use App\Filament\Resources\Children\Pages\ListChildren;
use App\Models\Child;
use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => Setting::set('catalog_open', true));

it('keeps child profiles closed until the catalogue opens', function () {
    Setting::set('catalog_open', false);
    $parent = User::factory()->create();

    $this->actingAs($parent)->get('/akaun')->assertOk()->assertDontSee('Profil anak');
    $this->actingAs($parent)->get('/akaun/anak/tambah')->assertNotFound();
});

it('asks for login and a verified e-mail', function () {
    $this->get('/akaun/anak/tambah')->assertRedirect('/login');
    $this->actingAs(User::factory()->unverified()->create())->get('/akaun/anak/tambah')->assertRedirect('/email/verify');
});

it('adds a child with only a nickname, age group and avatar', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)->get('/akaun/anak/tambah')->assertOk()->assertSee('Tiada foto, tarikh lahir atau sekolah.');

    $this->actingAs($parent)->post('/akaun/anak', [
        'nickname' => 'Aisyah',
        'age_group' => '6-9',
        'avatar' => 'anis',
        'birth_date' => '2019-01-01', // ignored: never stored
    ])->assertRedirect('/akaun');

    $child = $parent->children()->sole();
    expect($child->nickname)->toBe('Aisyah')
        ->and($child->age_group)->toBe(AgeGroup::SixToNine)
        ->and($child->avatar)->toBe(ChildAvatar::Anis)
        ->and(array_keys($child->getAttributes()))->not->toContain('birth_date');

    $this->actingAs($parent)->get('/akaun')->assertSee('Aisyah')->assertSee('6–9 tahun');
});

it('validates the profile', function (array $data, string $field) {
    $this->actingAs(User::factory()->create())
        ->post('/akaun/anak', $data + ['nickname' => 'Adik', 'age_group' => '10-13', 'avatar' => 'kucing'])
        ->assertSessionHasErrors($field);
})->with([
    'no nickname' => [['nickname' => ''], 'nickname'],
    'long nickname' => [['nickname' => str_repeat('a', 31)], 'nickname'],
    'both ages' => [['age_group' => 'both'], 'age_group'],
    'unknown avatar' => [['avatar' => 'gambar-saya.jpg'], 'avatar'],
]);

it('allows at most four children per account', function () {
    $parent = User::factory()->create();
    Child::factory()->count(4)->for($parent)->create();

    $this->actingAs($parent)->get('/akaun')->assertDontSee('Tambah anak');
    $this->actingAs($parent)->get('/akaun/anak/tambah')->assertRedirect('/akaun');
    $this->actingAs($parent)
        ->post('/akaun/anak', ['nickname' => 'Kelima', 'age_group' => '6-9', 'avatar' => 'unta'])
        ->assertRedirect('/akaun');

    expect($parent->children()->count())->toBe(4);
});

it('edits and deletes a child profile', function () {
    $child = Child::factory()->create(['nickname' => 'Along', 'age_group' => AgeGroup::SixToNine]);
    $parent = $child->user;

    $this->actingAs($parent)->get("/akaun/anak/{$child->id}/ubah")->assertOk()->assertSee('Padam profil Along');

    $this->actingAs($parent)
        ->put("/akaun/anak/{$child->id}", ['nickname' => 'Along', 'age_group' => '10-13', 'avatar' => 'penyu'])
        ->assertRedirect('/akaun');
    expect($child->refresh()->age_group)->toBe(AgeGroup::TenToThirteen);

    $this->actingAs($parent)->delete("/akaun/anak/{$child->id}")->assertRedirect('/akaun');
    expect(Child::query()->find($child->id))->toBeNull();
});

it('hides other families\' children', function () {
    $child = Child::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get("/akaun/anak/{$child->id}/ubah")->assertNotFound();
    $this->actingAs($stranger)->put("/akaun/anak/{$child->id}", ['nickname' => 'X', 'age_group' => '6-9', 'avatar' => 'anis'])->assertNotFound();
    $this->actingAs($stranger)->delete("/akaun/anak/{$child->id}")->assertNotFound();

    expect($child->fresh())->not->toBeNull();
});

it('removes children with the parent account', function () {
    $child = Child::factory()->create();

    $child->user->delete();

    expect(Child::query()->count())->toBe(0);
});

it('lists child profiles read-only for the super admin only', function () {
    $child = Child::factory()->create(['nickname' => 'Usu']);

    $this->actingAs(User::factory()->role(Role::Editor)->create())->get('/admin/profil-anak')->assertForbidden();

    $this->actingAs(User::factory()->admin()->create());
    $this->get('/admin/profil-anak')->assertOk();
    Livewire::test(ListChildren::class)->assertCanSeeTableRecords([$child]);
    $this->get("/admin/pelanggan/{$child->user_id}")->assertSee('Usu');
});
