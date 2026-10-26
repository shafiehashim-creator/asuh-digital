<?php

use App\Models\Book;
use App\Models\Child;
use App\Models\Plan;
use App\Models\ReadingSession;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Setting::set('catalog_open', true);
});

it('links the guide from the account menu and summary', function () {
    $this->actingAs(User::factory()->create())->get('/akaun')
        ->assertOk()
        ->assertSee('Panduan penggunaan')
        ->assertSee(route('account.guide'));
});

it('walks parents through each step with their own checklist', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)->get(route('account.guide'))->assertOk()
        ->assertSee('Panduan ASUH Digital')
        ->assertSeeInOrder(['Tambah profil anak', 'Tetapkan PIN ibu bapa', 'Serahkan peranti dalam Mod Anak', 'Membaca, kuiz dan lencana', 'Keluar dari Mod Anak', 'Pantau kemajuan anak', 'Pasang app'])
        ->assertSee('0 / 4 siap')
        ->assertSee(route('children.create'));

    $child = Child::factory()->for($parent)->create();
    $parent->forceFill(['parent_pin' => '4821'])->save();
    ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => Book::factory()->published()->create()->id, 'read_on' => today(), 'minutes' => 6, 'last_page' => 2]);

    $this->get(route('account.guide'))->assertSee('3 / 4 siap');
});

it('explains the video controls once the videos open, with read first as an option', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('account.guide'))->assertDontSee('Video animasi dan kawalan menonton');

    Setting::set('videos_open', true);
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 14900]);

    $this->get(route('account.guide'))
        ->assertSee('Video animasi dan kawalan menonton')
        ->assertSee('(pilihan, dimatikan secara asal)', false);
});
