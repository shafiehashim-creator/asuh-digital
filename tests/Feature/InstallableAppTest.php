<?php

use App\Models\Child;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PlanSeeder;

beforeEach(fn () => $this->seed(PlanSeeder::class));

it('serves the app manifest with the shortcuts that are open', function () {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', 'ASUH Digital')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('shortcuts', []);

    Setting::set('catalog_open', true);
    Setting::set('videos_open', true);
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 15900]);

    $names = collect($this->get('/manifest.webmanifest')->json('shortcuts'))->pluck('url')->all();
    expect($names)->toBe([route('account.children', absolute: false), route('videos.index', absolute: false), route('catalog.index', absolute: false)]);
});

it('serves the manifest in English when chosen', function () {
    $this->get(route('locale.switch', 'en'));

    $this->get('/manifest.webmanifest')
        ->assertJsonPath('lang', 'en')
        ->assertJsonPath('description', 'A digital Islamic bookshelf for children aged 6–13. Jom Jadi Baik!');
});

it('links the manifest on every page and ships a service worker and offline page', function () {
    $this->get('/')->assertSee('<link rel="manifest" href="/manifest.webmanifest" crossorigin="use-credentials">', false);

    expect(file_get_contents(public_path('sw.js')))->toContain('/offline.html')
        ->and(file_get_contents(public_path('offline.html')))->toContain('Tiada sambungan internet');
});

it('shows how to install the app', function () {
    $this->get('/pasang')->assertOk()
        ->assertSee('Pasang app ASUH')
        ->assertSee('iPhone dan iPad (Safari)')
        ->assertSee('installApp', false);

    $this->get('/')->assertSee(route('install'));
});

it('puts Video in the phone tab bar once the videos open, and names the account tab Keluarga', function () {
    Setting::set('catalog_open', true);
    $this->actingAs(User::factory()->create());

    $tabs = fn () => $this->get('/')->getContent();
    expect($tabs())->toContain('Keluarga')->toContain('Buku')->not->toContain(route('videos.index'));

    Setting::set('videos_open', true);
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 15900]);
    expect($tabs())->toContain(route('videos.index'));
});

it('lets the installed app read its manifest while Mod Anak is on', function () {
    Setting::set('catalog_open', true);
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create();
    $this->actingAs($parent)->post("/akaun/anak/{$child->id}/mod-anak");

    $this->get('/manifest.webmanifest')->assertOk()->assertJsonPath('short_name', 'ASUH');
    $this->get('/pasang')->assertRedirect('/mod-anak');
});
