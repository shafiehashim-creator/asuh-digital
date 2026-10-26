<?php

use App\Enums\Role;
use App\Filament\Resources\Campaigns\Pages\ManageCampaigns;
use App\Models\Campaign;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(PlanSeeder::class));

function campaign(array $overrides = []): Campaign
{
    return Campaign::query()->create(array_merge([
        'title' => 'Jualan PBAKL',
        'message' => 'Diskaun istimewa sempena PBAKL.',
        'cta_label' => 'Langgan sekarang',
        'cta_url' => '/langganan',
        'promo_code' => 'PBAKL20',
        'ends_at' => now()->addDays(2)->addHours(3),
    ], $overrides));
}

it('shows the bar on every public page and the big countdown on the sales pages', function () {
    // Dates are stored to the second.
    $this->freezeSecond();
    campaign();

    $this->get('/tentang-kami')->assertOk()
        ->assertSee('data-campaign-bar', false)
        ->assertSee('Jualan PBAKL')
        ->assertSee('Tamat dalam')
        ->assertSee('2 hari')
        ->assertSee('PBAKL20')
        ->assertDontSee('data-campaign-banner', false);

    foreach (['/', route('pass.show')] as $url) {
        $this->get($url)->assertOk()
            ->assertSee('data-campaign-banner', false)
            ->assertSee('Diskaun istimewa sempena PBAKL.')
            ->assertSee('href="/langganan"', false)
            ->assertSee('countdown('.(2 * 86400 + 3 * 3600), false);
    }
});

it('shows nothing before it starts, after it ends or when switched off', function () {
    campaign(['starts_at' => now()->addDay()]);
    campaign(['ends_at' => now()->subMinute()]);
    campaign(['is_active' => false]);

    $this->get('/')->assertDontSee('Jualan PBAKL')->assertDontSee('data-campaign-bar', false);
});

it('follows the places chosen and shows the campaign ending first', function () {
    campaign(['title' => 'Kempen Lama', 'ends_at' => now()->addDays(10), 'show_banner' => false]);
    campaign(['title' => 'Hujung Minggu', 'ends_at' => now()->addDay(), 'show_bar' => false]);

    $this->get('/')
        ->assertSee('Kempen Lama')
        ->assertSee('Hujung Minggu')
        ->assertSeeInOrder(['data-campaign-bar', 'Kempen Lama', 'data-campaign-banner', 'Hujung Minggu'], false);
});

it('uses the English text when the visitor reads in English', function () {
    campaign(['title_en' => 'PBAKL Sale', 'message_en' => null]);

    $this->get(route('locale.switch', 'en'));
    $this->get('/')
        ->assertSee('PBAKL Sale')
        ->assertSee('Diskaun istimewa sempena PBAKL.')
        ->assertSee('Ends in');
});

it('lets a Super Admin create a campaign, and only a Super Admin', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageCampaigns::class)
        ->callAction('create', data: [
            'title' => 'Pelancaran ASUH', 'promo_code' => 'launch10', 'cta_url' => '/langganan',
            'ends_at' => now()->addWeek()->format('Y-m-d H:i'), 'show_bar' => true, 'show_banner' => true, 'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(Campaign::query()->sole())->promo_code->toBe('LAUNCH10');
    $this->get('/admin/kempen')->assertOk()->assertSee('Pelancaran ASUH')->assertSee('Berjalan');

    Livewire::test(ManageCampaigns::class)
        ->callAction('create', data: ['title' => 'Salah', 'cta_url' => 'javascript:alert(1)', 'ends_at' => now()->addDay()->format('Y-m-d H:i')])
        ->assertHasActionErrors(['cta_url']);

    $this->actingAs(User::factory()->role(Role::Editor)->create())->get('/admin/kempen')->assertForbidden();
});
