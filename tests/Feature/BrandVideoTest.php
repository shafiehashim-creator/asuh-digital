<?php

use App\Models\Child;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    Setting::set('catalog_open', true);
    Setting::set('brand_video_url', 'https://youtu.be/dQw4w9WgXcQ');
    Setting::set('brand_video_title', 'Lagu ASUH: Jom Jadi Baik!');
});

it('shows the music video on the home, about and account pages, loading YouTube only on play', function () {
    $this->get('/')->assertOk()->assertSee('Jom nyanyi bersama Anis dan Anas')
        ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0', false);
    $this->get('/tentang-kami')->assertSee('Lagu ASUH: Jom Jadi Baik!');
    $this->actingAs(User::factory()->create())->get('/akaun')->assertSee('Lagu ASUH: Jom Jadi Baik!');
});

it('shows it only on the pages ticked in the settings', function () {
    Setting::set('brand_video_places', ['about']);

    $this->get('/')->assertDontSee('Jom nyanyi bersama Anis dan Anas');
    $this->get('/tentang-kami')->assertSee('dQw4w9WgXcQ');
});

it('hides it everywhere without a valid YouTube link', function () {
    Setting::set('brand_video_url', null);

    $this->get('/')->assertDontSee('Jom nyanyi bersama Anis dan Anas');
    $this->get('/tentang-kami')->assertDontSee('youtube-nocookie');
});

it('plays in Mod Anak on the ASUH player for every child', function () {
    $child = Child::factory()->create();
    $this->actingAs($child->user)->post("/akaun/anak/{$child->id}/mod-anak");

    $this->get('/mod-anak')->assertSee(route('child-mode.song'), false);
    $this->get('/mod-anak/lagu')->assertOk()
        ->assertSee('data-provider="youtube"', false)
        ->assertSee('data-video-id="dQw4w9WgXcQ"', false);

    Setting::set('brand_video_places', ['home']);
    $this->get('/mod-anak/lagu')->assertRedirect(route('child-mode.home'));
});
