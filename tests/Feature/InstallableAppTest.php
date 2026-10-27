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
    Setting::set('yearly_includes_videos', false);
    Setting::set('plus_sales_open', true);
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
    Setting::set('yearly_includes_videos', false);
    Setting::set('plus_sales_open', true);
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

it('has a phone menu drawer with the public, family and school links', function () {
    $this->get('/')->assertOk()
        ->assertSee("\$dispatch('open-menu')", false)
        ->assertSee('Tentang ASUH')
        ->assertSee(route('pass.show'))
        ->assertSee(route('pupil.join'))
        ->assertSee(route('pilot.create'))
        ->assertSee('Daftar percuma')
        ->assertDontSee('Keluarga saya')
        ->assertDontSee(route('plus.show'));

    Setting::set('catalog_open', true);
    Setting::set('videos_open', true);
    Setting::set('yearly_includes_videos', false);
    Setting::set('plus_sales_open', true);
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 15900]);
    $this->actingAs(User::factory()->create());

    $this->get('/')
        ->assertSee('Keluarga saya')
        ->assertSee(route('account.children'))
        ->assertSee(route('plus.show'))
        ->assertSee(route('videos.index'))
        ->assertDontSee('Daftar percuma');
});

it('keeps the app tab bar on phones and tablets and gives computers the full menu', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // The tab bar, the drawer and the slim header stop at 1024px (lg), not 768px.
    expect($html)->toContain('pb-[env(safe-area-inset-bottom)] lg:hidden')
        ->toContain('class="hidden items-center gap-1 text-sm font-bold lg:flex"');

    // Before the videos open: Langganan is a plain link, Sekolah a dropdown.
    $this->get('/')
        ->assertSee('href="'.route('pass.show').'"', false)
        ->assertSee('Sekolah')
        ->assertSee('aria-haspopup="true"', false)
        ->assertSee(route('teacher.home'))
        ->assertSee(route('about'));

    Setting::set('catalog_open', true);
    Setting::set('videos_open', true);
    Setting::set('yearly_includes_videos', false);
    Setting::set('plus_sales_open', true);
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 15900]);

    $html = $this->get(route('plus.show'))
        ->assertSee(route('catalog.index'))
        ->assertSee(route('videos.index'))
        ->getContent();
    expect($html)->toMatch('#href="'.preg_quote(route('plus.show'), '#').'"\s+aria-current="page"#');
});
