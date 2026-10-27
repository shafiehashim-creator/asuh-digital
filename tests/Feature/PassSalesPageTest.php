<?php

use App\Enums\AgeGroup;
use App\Enums\PreorderStatus;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Models\Book;
use App\Models\Plan;
use App\Models\Preorder;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    // The sums below use the earlier RM99 yearly price (RM149 since 2026-10-27).
    Plan::query()->where('code', 'pas_tahunan')->update(['price_sen' => 9900]);
});

function salesPageRegularPrices(): void
{
    // Early bird over: the regular yearly price and the monthly plan are on sale.
    Setting::set('preorder_slot_limit', 1);
    Preorder::factory()->status(PreorderStatus::Paid)->create();
}

it('leads with the early bird price per month and the regular price it saves against', function () {
    $this->get(route('pass.show'))->assertOk()
        ->assertSee('Tukar masa skrin jadi masa membaca.')
        ->assertSeeInOrder(['Early bird · 12 bulan', 'RM6.58', 'sebulan', 'Dibayar sekali setahun:', 'RM79'])
        ->assertSee('Jimat 20%')
        ->assertSee('Jimat RM20.00.')
        ->assertSee('Tempah slot saya')
        ->assertDontSee('1 bulan');
});

it('shows the monthly plan, the yearly saving and the year in bars once the early bird is over', function () {
    salesPageRegularPrices();

    $this->get(route('pass.show'))->assertOk()
        ->assertSeeInOrder(['Langganan 12 bulan', 'RM8.25', 'Bayar bulanan, 12 bulan', 'RM178.80', 'Jimat RM79.80.'])
        ->assertSee('Jimat 45%')
        ->assertSeeInOrder(['Semua pelan', '1 bulan', 'RM14.90', '12 bulan', 'RM99 / tahun'])
        ->assertSee('Macam dapat 5 bulan percuma.')
        ->assertSee('Tahunan atau bulanan, mana satu?')
        ->assertSee(route('purchase.pass', ['tempoh' => 'bulanan']), false);
});

it('shows the books for each age group once the catalogue is open', function () {
    Book::factory()->published()->create(['title' => 'Buku Adik', 'age_group' => AgeGroup::SixToNine]);
    Book::factory()->published()->create(['title' => 'Buku Abang', 'age_group' => AgeGroup::TenToThirteen]);

    $this->get(route('pass.show'))->assertDontSee('Untuk umur');

    Setting::set('catalog_open', true);
    $this->get(route('pass.show'))->assertOk()
        ->assertSeeInOrder(['Untuk umur 6–9 tahun', 'Buku Adik', 'Untuk umur 10–13 tahun', 'Buku Abang']);
});

it('shows testimonials only once two are published, the first quote under the price', function () {
    Testimonial::create(['author' => 'Puan Aishah', 'detail' => 'Ibu 2 anak, Johor', 'quote' => 'Anak minta nak baca lagi!', 'is_published' => true]);
    Testimonial::create(['author' => 'Draf', 'quote' => 'Belum disemak', 'is_published' => false]);

    $this->get(route('pass.show'))->assertOk()
        ->assertSee('Anak minta nak baca lagi!')
        ->assertSee('Puan Aishah · Ibu 2 anak, Johor')
        ->assertDontSee('Dari keluarga ASUH')
        ->assertDontSee('Belum disemak');

    Testimonial::create(['author' => 'Encik Hafiz', 'quote' => 'Laporan mingguan sangat membantu.', 'is_published' => true]);

    $this->get(route('pass.show'))->assertOk()
        ->assertSee('Dari keluarga ASUH')
        ->assertSee('Laporan mingguan sangat membantu.');
});

it('lets the Super Admin add testimonials, with a quote or a screenshot required', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageTestimonials::class)
        ->callAction('create', ['author' => 'Puan Aishah', 'quote' => '', 'image_path' => null])
        ->assertHasActionErrors(['quote']);

    Livewire::test(ManageTestimonials::class)
        ->callAction('create', ['author' => 'Puan Aishah', 'quote' => 'Bagus!', 'is_published' => true])
        ->assertHasNoActionErrors();

    expect(Testimonial::query()->sole()->is_published)->toBeTrue();

    $this->actingAs(User::factory()->create(['role' => 'editor']))->get('/admin/testimoni')->assertForbidden();
});

it('says when the yearly subscription is not on sale', function () {
    Plan::query()->update(['is_active' => false]);

    $this->get(route('pass.show'))->assertOk()
        ->assertSee('Langganan Tahunan belum dijual buat masa ini.')
        ->assertDontSee('Serendah');
});
