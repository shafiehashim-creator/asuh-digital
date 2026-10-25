<?php

use App\Enums\OrderStatus;
use App\Enums\PassStartRule;
use App\Enums\PreorderStatus;
use App\Enums\Role;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Preorders\Pages\ListPreorders;
use App\Models\Order;
use App\Models\Pass;
use App\Models\PilotRequest;
use App\Models\Plan;
use App\Models\Preorder;
use App\Models\Setting;
use App\Models\User;
use App\Services\DashboardStats;
use App\Services\PaymentProcessor;
use Database\Seeders\PlanSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

function validSettings(array $overrides = []): array
{
    return array_merge([
        'early_bird_price' => '69.00',
        'regular_price' => '109.00',
        'preorder_slot_limit' => 150,
        'preorder_reservation_minutes' => 45,
        'bank_transfer_reservation_hours' => 48,
        'pass_start_rule' => PassStartRule::OnPayment->value,
        'launch_date' => null,
        'landing_headline' => 'Tajuk baharu',
        'landing_subheadline' => 'Subtajuk baharu',
        'pilot_duration_days' => 60,
        'refund_policy' => 'Polisi baharu',
        'landing_faq' => [['question' => 'Soalan A?', 'answer' => 'Jawapan A.']],
        'bank_name' => 'Maybank',
        'bank_account_name' => 'Galeri Ilmu Media',
        'bank_account_number' => '5140 1234 5678',
        'company_name' => 'Syarikat Ujian Sdn Bhd',
        'company_ssm_no' => '202601000001',
        'company_sst_no' => null,
        'contact_email' => 'tanya@example.com',
        'company_address' => "Jalan Ujian\nSelangor",
        'admin_notification_emails' => ['admin@example.com'],
    ], $overrides);
}

// --- Settings page ---------------------------------------------------------

it('loads current values into the settings form', function () {
    $this->actingAs($this->admin);

    Livewire::test(Settings::class)
        ->assertOk()
        ->assertSchemaStateSet([
            'early_bird_price' => '79.00',
            'regular_price' => '99.00',
            'preorder_slot_limit' => 100,
            'landing_headline' => 'Rak buku Islamik digital untuk anak 6–13 tahun',
        ]);
});

it('saves settings and prices from the admin page', function () {
    $this->actingAs($this->admin);

    Livewire::test(Settings::class)
        ->fillForm(validSettings())
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Tetapan disimpan.');

    expect(Plan::findByCode(Plan::PAS_TAHUNAN_EARLY)->price_sen)->toBe(6900)
        ->and(Plan::findByCode(Plan::PAS_TAHUNAN)->price_sen)->toBe(10900)
        ->and(Setting::get('preorder_slot_limit'))->toBe(150)
        ->and(Setting::get('landing_faq'))->toBe([['question' => 'Soalan A?', 'answer' => 'Jawapan A.']])
        ->and(Setting::get('admin_notification_emails'))->toBe(['admin@example.com'])
        ->and(Setting::get('company_name'))->toBe('Syarikat Ujian Sdn Bhd')
        ->and(Setting::get('pass_start_rule'))->toBe('on_payment');

    // The public site follows straight away.
    $this->get('/')->assertSee('Tajuk baharu')->assertSee('RM69')->assertSee('Soalan A?');
});

it('does not change what earlier orders were charged when the price changes', function () {
    $order = Order::factory()->paid()->create(['amount_sen' => 7900]);
    $this->actingAs($this->admin);

    Livewire::test(Settings::class)->fillForm(validSettings())->call('save');

    expect($order->fresh()->amount_sen)->toBe(7900);
});

it('rejects invalid values', function (array $bad, string $field) {
    $this->actingAs($this->admin);

    Livewire::test(Settings::class)
        ->fillForm(validSettings($bad))
        ->call('save')
        ->assertHasFormErrors([$field]);

    expect(Setting::get('preorder_slot_limit'))->toBe(100);
})->with([
    'no price' => [['early_bird_price' => null], 'early_bird_price'],
    'negative slots' => [['preorder_slot_limit' => -1], 'preorder_slot_limit'],
    'bad contact e-mail' => [['contact_email' => 'bukan-emel'], 'contact_email'],
    'bad admin e-mail' => [['admin_notification_emails' => ['bukan-emel']], 'admin_notification_emails.0'],
    'empty headline' => [['landing_headline' => ''], 'landing_headline'],
]);

it('schedules waiting passes once the launch date is set', function () {
    Setting::set('pass_start_rule', 'on_launch');
    $order = Order::factory()->create();
    app(PaymentProcessor::class)->markPaid($order);
    expect($order->pass->starts_at)->toBeNull();

    $this->actingAs($this->admin);

    Livewire::test(Settings::class)
        ->fillForm(validSettings(['pass_start_rule' => 'on_launch', 'launch_date' => '2027-03-01']))
        ->call('save')
        ->assertNotified();

    expect($order->pass->fresh()->starts_at->toDateString())->toBe('2027-03-01')
        ->and($order->pass->fresh()->ends_at->toDateString())->toBe('2028-03-01');
});

it('starts waiting passes from the payment date when switching back to "on payment"', function () {
    Setting::set('pass_start_rule', 'on_launch');
    $order = Order::factory()->create();
    app(PaymentProcessor::class)->markPaid($order);
    $paidAt = $order->fresh()->paid_at;

    Setting::set('pass_start_rule', 'on_payment');
    $scheduled = app(PaymentProcessor::class)->scheduleWaitingPasses();

    expect($scheduled)->toBe(1)
        ->and($order->pass->fresh()->starts_at->equalTo($paidAt))->toBeTrue();
});

it('keeps the settings page for the Super Admin only', function (Role $role) {
    $this->actingAs(User::factory()->role($role)->create())
        ->get('/admin/tetapan')
        ->assertForbidden();
})->with([Role::Editor, Role::Parent]);

// --- Pre-order list ----------------------------------------------------------

it('lists pre-orders for the admin and filters by status', function () {
    $this->actingAs($this->admin);
    $paid = Preorder::factory()->status(PreorderStatus::Paid)->create();
    $reserved = Preorder::factory()->create();

    Livewire::test(ListPreorders::class)
        ->assertCanSeeTableRecords([$paid, $reserved])
        ->filterTable('status', PreorderStatus::Paid->value)
        ->assertCanSeeTableRecords([$paid])
        ->assertCanNotSeeTableRecords([$reserved]);
});

it('keeps editors out of the pre-order list', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create())
        ->get('/admin/pre-order')
        ->assertForbidden();
});

// --- Dashboard ---------------------------------------------------------------

it('counts pre-orders against the target', function () {
    Preorder::factory()->status(PreorderStatus::Paid)->count(3)->create();
    Preorder::factory()->status(PreorderStatus::AwaitingConfirmation)->create();
    Preorder::factory()->create();
    Preorder::factory()->expiredReservation()->create();

    expect(app(DashboardStats::class)->preorders())->toBe([
        'limit' => 100, 'paid' => 3, 'awaiting' => 1, 'reserved' => 1, 'remaining' => 95,
    ]);
});

it('sums paid sales for today, this week and this month', function () {
    $this->travelTo(now()->setDate(2026, 10, 28)->setTime(12, 0)); // a Wednesday

    Order::factory()->paid()->create(['amount_sen' => 7900, 'paid_at' => now()]);
    Order::factory()->paid()->create(['amount_sen' => 7900, 'paid_at' => now()->subDays(2)]); // Monday
    Order::factory()->paid()->create(['amount_sen' => 9900, 'paid_at' => now()->setDate(2026, 10, 2)]);
    Order::factory()->paid()->create(['amount_sen' => 9900, 'paid_at' => now()->subMonth()]);
    Order::factory()->create(['amount_sen' => 7900, 'status' => OrderStatus::Pending]);

    expect(app(DashboardStats::class)->sales())->toBe([
        'today' => 7900, 'week' => 15800, 'month' => 25700,
    ]);
});

it('counts active, expiring and waiting passes and pilot conversion', function () {
    $make = function (?string $starts, ?string $ends) {
        $order = Order::factory()->paid()->create();
        Pass::query()->create([
            'user_id' => $order->user_id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
            'starts_at' => $starts, 'ends_at' => $ends,
        ]);
    };
    $make(now()->subDay(), now()->addYear());
    $make(now()->subYear(), now()->addDays(10));
    $make(null, null);
    $make(now()->subYears(2), now()->subYear());

    PilotRequest::factory()->count(2)->create();
    PilotRequest::factory()->create(['status' => 'pilot_active']);
    PilotRequest::factory()->create(['status' => 'paying']);

    $stats = app(DashboardStats::class);

    expect($stats->passes())->toBe(['active' => 2, 'expiring' => 1, 'waiting' => 1])
        ->and($stats->pilots())->toBe(['total' => 4, 'active' => 1, 'paying' => 1]);
});

it('shows the dashboard widgets to the admin', function () {
    $this->actingAs($this->admin);

    Livewire::test(App\Filament\Widgets\SalesOverview::class)->assertSee('Pre-order dibayar')->assertSee('0 / 100');
    Livewire::test(App\Filament\Widgets\PassesAndPilotsOverview::class)->assertSee('Langganan Tahunan aktif');
});

it('hides financial widgets from editors', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    expect(App\Filament\Widgets\SalesOverview::canView())->toBeFalse()
        ->and(App\Filament\Widgets\PassesAndPilotsOverview::canView())->toBeFalse();
});

it('shows the CHIP connection status without revealing the keys', function (?string $brand, ?string $key, bool $testMode, string $expected) {
    config([
        'services.chip.brand_id' => $brand,
        'services.chip.secret_key' => $key,
        'services.chip.test_mode' => $testMode,
    ]);
    $this->actingAs($this->admin);

    Livewire::test(Settings::class)
        ->assertSee($expected)
        ->assertDontSee('rahsia-kunci-chip');
})->with([
    'not set' => [null, null, true, 'CHIP: belum disambungkan'],
    'test mode' => ['brand-1', 'rahsia-kunci-chip', true, 'CHIP: disambungkan (mod ujian)'],
    'live' => ['brand-1', 'rahsia-kunci-chip', false, 'CHIP: disambungkan (live)'],
]);
