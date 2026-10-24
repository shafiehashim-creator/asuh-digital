<?php

use App\Models\Setting;

it('renders the privacy notice and terms inside the full site layout', function (string $url, string $title) {
    // Regression: a Blade directive glued to a word ("permintaan@if") once
    // broke the component and served the page without its layout.
    $this->get($url)
        ->assertOk()
        ->assertSee('<!DOCTYPE html>', false)
        ->assertSee($title.' · ASUH Digital', false)
        ->assertSee('Notis privasi')
        ->assertSee('Terma dan syarat');
})->with([
    'privacy' => ['/privasi', 'Notis privasi'],
    'terms' => ['/terma', 'Terma dan syarat'],
]);

it('covers what the privacy notice must say about children and payments', function () {
    $this->get('/privasi')
        ->assertSee('Akta Perlindungan Data Peribadi 2010')
        ->assertSee('Anak tidak mempunyai e-mel atau kata laluan sendiri.')
        ->assertSee('CHIP')
        ->assertSee('tidak menggunakan kuki analitik');
});

it('uses company details and the contact e-mail from settings', function () {
    Setting::set('company_name', 'Syarikat Ujian Sdn Bhd');
    Setting::set('contact_email', 'tanya@example.com');

    $this->get('/privasi')
        ->assertSee('Syarikat Ujian Sdn Bhd')
        ->assertSee('mailto:tanya@example.com', false);

    $this->get('/terma')->assertSee('Syarikat Ujian Sdn Bhd');
});

it('falls back to the brand name and receipt e-mail when settings are empty', function () {
    $this->get('/privasi')
        ->assertSee('ASUH, Galeri Ilmu Media Group')
        ->assertSee('hubungi kami melalui e-mel yang tertera pada resit anda');
});

it('keeps the terms in step with the refund policy, slot limit and pass rule', function () {
    Setting::set('refund_policy', 'Polisi ujian: bayaran balik dalam 7 hari.');
    Setting::set('preorder_slot_limit', 150);
    Setting::set('pass_start_rule', 'on_launch');

    $this->get('/terma')
        ->assertSee('Polisi ujian: bayaran balik dalam 7 hari.')
        ->assertSee('terhad kepada 150 slot')
        ->assertSee('bermula pada tarikh platform ASUH Digital dilancarkan');
});

it('shows the draft notice outside production only', function () {
    $this->get('/privasi')->assertSee('Draf: teks ini perlu disemak');

    app()->detectEnvironment(fn () => 'production');

    $this->get('/privasi')->assertDontSee('Draf: teks ini perlu disemak');
});

it('links the terms and privacy notice from the checkout consent', function () {
    $this->seed(Database\Seeders\PlanSeeder::class);

    $this->get('/pre-order')
        ->assertSee(route('terms'), false)
        ->assertSee(route('privacy'), false);
});
