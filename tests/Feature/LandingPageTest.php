<?php

use App\Enums\PreorderStatus;
use App\Models\Plan;
use App\Models\Preorder;
use App\Models\Setting;
use App\Services\PreorderSlots;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    // The sums below use the earlier RM99 yearly price (RM149 since 2026-10-27).
    Plan::query()->where('code', 'pas_tahunan')->update(['price_sen' => 9900]);
});

it('shows the early bird offer', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('E-book dan video animasi Islamik untuk anak 6–13 tahun')
        ->assertSee('RM79')
        ->assertSee('RM99')
        ->assertSee('Tempah slot saya');
});

it('follows a price change made by the admin', function () {
    Plan::findByCode(Plan::PAS_TAHUNAN_EARLY)->update(['price_sen' => 6900]);

    $this->get('/')->assertSee('RM69')->assertDontSee('RM79');
});

it('does not show a discouraging "0 sold" counter before the first sale', function () {
    $this->get('/')
        ->assertSee('Terhad kepada')
        ->assertSee('100 keluarga')
        ->assertDontSee('Tinggal');
});

it('counts down the remaining slots, ignoring expired reservations', function () {
    Preorder::factory()->status(PreorderStatus::Paid)->count(2)->create();
    Preorder::factory()->status(PreorderStatus::AwaitingConfirmation)->create();
    Preorder::factory()->expiredReservation()->count(5)->create();

    $this->get('/')->assertSeeInOrder(['Tinggal', '97', 'daripada 100 slot']);
});

it('switches to the regular pass at full price once every slot is taken', function () {
    Setting::set('preorder_slot_limit', 2);
    Preorder::factory()->status(PreorderStatus::Paid)->count(2)->create();

    $this->get('/')
        ->assertSee('Beli Langganan Tahunan')
        ->assertSee(route('purchase.pass'), false)
        ->assertSee('RM99')
        ->assertDontSee('Tempah slot saya')
        ->assertDontSee('Slot early bird telah habis');
});

it('closes the offer when slots are gone and the regular pass is not on sale', function () {
    Setting::set('preorder_slot_limit', 2);
    Preorder::factory()->status(PreorderStatus::Paid)->count(2)->create();
    Plan::findByCode(Plan::PAS_TAHUNAN)->update(['is_active' => false]);

    $this->get('/')
        ->assertSee('Slot early bird telah habis')
        ->assertSee('Semua 2 slot telah ditempah')
        ->assertDontSee('Tempah slot saya');
});

it('never reports negative slots when the limit is lowered below sales', function () {
    Preorder::factory()->status(PreorderStatus::Paid)->count(3)->create();
    Setting::set('preorder_slot_limit', 2);

    expect(app(PreorderSlots::class)->remaining())->toBe(0)
        ->and(app(PreorderSlots::class)->isSoldOut())->toBeTrue();
});

it('uses the headline, FAQ and refund policy from settings', function () {
    Setting::set('landing_headline', 'Tajuk baharu dari admin');
    Setting::set('landing_faq', [['question' => 'Soalan ujian?', 'answer' => 'Jawapan ujian.']]);
    Setting::set('refund_policy', 'Polisi bayaran balik ujian.');

    $this->get('/')
        ->assertSee('Tajuk baharu dari admin')
        ->assertSee('Soalan ujian?')
        ->assertSee('Jawapan ujian.')
        ->assertSee('Polisi bayaran balik ujian.');
});

it('explains when the pass starts according to the admin rule', function () {
    $this->get('/')->assertSee('bermula sebaik sahaja bayaran anda disahkan');

    Setting::set('pass_start_rule', 'on_launch');

    $this->get('/')->assertSee('bermula pada tarikh platform ASUH Digital dilancarkan');
});

it('shows the institution pilot length from settings', function () {
    Setting::set('pilot_duration_days', 45);

    $this->get('/')->assertSeeInOrder(['secara percuma selama', '45 hari']);
});

it('can be installed on a phone and shows an app-style tab bar', function () {
    $this->get('/')
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest" crossorigin="use-credentials">', false)
        ->assertSee('aria-label="Menu utama"', false)
        ->assertSeeInOrder(['Utama', 'Log masuk']);

    $manifest = $this->get('/manifest.webmanifest')->json();

    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/');
    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});
