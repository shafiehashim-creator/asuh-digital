<?php

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\Role;
use App\Filament\Resources\AffiliateCommissions\Pages\ListAffiliateCommissions;
use App\Filament\Resources\Affiliates\Pages\ManageAffiliates;
use App\Mail\AffiliateApplied;
use App\Mail\AffiliateApproved;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Affiliates;
use App\Services\PaymentProcessor;
use App\Services\PromoCodes;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    // The sums below use the earlier RM99 yearly price (RM149 since 2026-10-27).
    Plan::query()->where('code', 'pas_tahunan')->update(['price_sen' => 9900]);
    Mail::fake();
    Setting::set('affiliates_open', true);
    Setting::set('admin_notification_emails', ['admin@asuh.test']);
    Setting::set('preorder_slot_limit', 0);
});

function approvedAffiliate(array $attributes = []): Affiliate
{
    $user = User::factory()->create(['name' => 'Aminah Rahman']);
    $affiliate = app(Affiliates::class)->apply($user, ['promotion_channels' => 'Instagram', 'bank_name' => 'Maybank', 'bank_account_name' => 'Aminah', 'bank_account_number' => '1234567890']);
    $affiliate->forceFill($attributes + ['status' => AffiliateStatus::Approved, 'approved_at' => now()])->save();

    return $affiliate->fresh();
}

it('lets an account holder apply and tells the admin', function () {
    $user = User::factory()->create(['name' => 'Siti Aishah']);

    $this->actingAs($user)->get('/akaun')->assertSee(route('account.affiliate'), false);
    $this->get('/akaun/affiliate')->assertOk()->assertSee('Mohon menjadi affiliate');

    $this->post('/akaun/affiliate', ['promotion_channels' => 'Kumpulan WhatsApp tadika', 'bank_name' => 'CIMB', 'bank_account_name' => 'Siti Aishah', 'bank_account_number' => '7012 3456'])
        ->assertRedirect('/akaun/affiliate');

    $affiliate = $user->affiliate()->sole();
    expect($affiliate->status)->toBe(AffiliateStatus::Pending)->and($affiliate->code)->toStartWith('SITIAISH');
    Mail::assertQueued(AffiliateApplied::class, fn ($mail) => $mail->hasTo('admin@asuh.test'));
    $this->get('/akaun/affiliate')->assertSee('Permohonan sedang disemak');
});

it('hides the affiliate page while applications are closed, except from existing affiliates', function () {
    Setting::set('affiliates_open', false);

    $this->actingAs(User::factory()->create())->get('/akaun/affiliate')->assertNotFound();
    $this->actingAs(approvedAffiliate()->user)->get('/akaun/affiliate')->assertOk();
});

it('approves from the admin panel and e-mails the link and code', function () {
    $affiliate = app(Affiliates::class)->apply(User::factory()->create(), ['promotion_channels' => 'FB', 'bank_name' => 'BSN', 'bank_account_name' => 'A', 'bank_account_number' => '1']);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageAffiliates::class)->callTableAction('approve', $affiliate);

    expect($affiliate->fresh()->status)->toBe(AffiliateStatus::Approved);
    Mail::assertQueued(AffiliateApproved::class);

    $this->actingAs($affiliate->user)->get('/akaun/affiliate')
        ->assertSee($affiliate->link())->assertSee($affiliate->code);
});

it('remembers a ?ref= link and credits the next order', function () {
    $affiliate = approvedAffiliate();

    $this->get('/?ref='.strtolower($affiliate->code))->assertCookie(PromoCodes::COOKIE, $affiliate->code);

    $buyer = User::factory()->create();
    $this->actingAs($buyer)->withCookie(PromoCodes::COOKIE, $affiliate->code)
        ->post('/beli/langganan', ['payment_method' => 'chip'])->assertRedirect();

    expect(Order::query()->sole()->affiliate_id)->toBe($affiliate->id);
});

it('credits the affiliate when the code is typed, and keeps a ref link alongside a coupon', function () {
    $affiliate = approvedAffiliate();
    Coupon::factory()->create(['code' => 'RAYA', 'value' => 10]);

    $this->actingAs(User::factory()->create())->post('/beli/langganan', ['payment_method' => 'chip', 'promo_code' => $affiliate->code]);
    expect(Order::query()->latest('id')->first())->affiliate_id->toBe($affiliate->id)->discount_sen->toBe(0);

    $this->actingAs(User::factory()->create())->withCookie(PromoCodes::COOKIE, $affiliate->code)
        ->post('/beli/langganan', ['payment_method' => 'chip', 'promo_code' => 'RAYA']);
    expect(Order::query()->latest('id')->first())->affiliate_id->toBe($affiliate->id)->discount_sen->toBe(990);
});

it('never credits affiliates for their own purchases', function () {
    $affiliate = approvedAffiliate();

    $this->actingAs($affiliate->user)->post('/beli/langganan', ['payment_method' => 'chip', 'promo_code' => $affiliate->code])
        ->assertSessionHasErrors(['promo_code' => 'Anda tidak boleh menggunakan kod affiliate anda sendiri.']);

    $this->actingAs($affiliate->user)->withCookie(PromoCodes::COOKIE, $affiliate->code)
        ->post('/beli/langganan', ['payment_method' => 'chip']);
    expect(Order::query()->sole()->affiliate_id)->toBeNull();
});

it('ignores codes of affiliates who are not approved', function () {
    $affiliate = approvedAffiliate(['status' => AffiliateStatus::Suspended]);

    $this->get('/?ref='.$affiliate->code)->assertCookieMissing(PromoCodes::COOKIE);
});

it('earns a commission once paid, due after the refund window, and is paid by the admin', function () {
    Setting::set('affiliate_commission_percent', 10);
    Setting::set('affiliate_hold_days', 14);
    $affiliate = approvedAffiliate();
    $order = Order::factory()->create(['amount_sen' => 9900, 'processing_fee_sen' => 100, 'affiliate_id' => $affiliate->id]);

    app(PaymentProcessor::class)->markPaid($order);
    app(PaymentProcessor::class)->markPaid($order->fresh()); // repeated callbacks do not pay twice

    $commission = AffiliateCommission::query()->sole();
    expect($commission->amount_sen)->toBe(990) // 10% of the price, not of the fee
        ->and($commission->isDue())->toBeFalse()
        ->and($commission->payable_at->toDateString())->toBe(now()->addDays(14)->toDateString());

    $this->travel(15)->days();
    expect($commission->fresh()->isDue())->toBeTrue();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Livewire::test(ManageAffiliates::class)->callTableAction('payDue', $affiliate, data: ['reference' => 'IBG123']);

    expect($commission->fresh())->status->toBe(CommissionStatus::Paid)->payment_reference->toBe('IBG123');
    $this->actingAs($affiliate->user)->get('/akaun/affiliate')->assertSee('RM9.90')->assertSee('Dibayar');
});

it('uses an affiliate\'s own rate and lets the admin cancel a commission', function () {
    $affiliate = approvedAffiliate(['commission_percent' => 15]);
    $order = Order::factory()->create(['amount_sen' => 10000, 'affiliate_id' => $affiliate->id]);
    app(PaymentProcessor::class)->markPaid($order);

    $commission = AffiliateCommission::query()->sole();
    expect($commission->amount_sen)->toBe(1500);

    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(ListAffiliateCommissions::class)->callTableAction('cancel', $commission);
    expect($commission->fresh()->status)->toBe(CommissionStatus::Cancelled);
});

it('keeps the affiliate admin for Super Admins only', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create());
    $this->get('/admin/affiliate')->assertForbidden();
    $this->get('/admin/komisen-affiliate')->assertForbidden();
});
