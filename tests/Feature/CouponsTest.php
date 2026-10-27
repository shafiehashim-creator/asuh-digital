<?php

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PromoProduct;
use App\Enums\Role;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Models\Book;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    // The sums below use the earlier RM99 yearly price (RM149 since 2026-10-27).
    Plan::query()->where('code', 'pas_tahunan')->update(['price_sen' => 9900]);
    Setting::set('preorder_slot_limit', 0); // regular price on sale
    $this->buyer = User::factory()->create();
});

function buyPass($test, string $code)
{
    return $test->actingAs($test->buyer)->post('/beli/langganan', ['payment_method' => 'chip', 'promo_code' => $code]);
}

it('takes a percentage coupon off and shows it on the pay page', function () {
    $coupon = Coupon::factory()->create(['code' => 'RAYA20', 'value' => 20]);

    buyPass($this, 'raya20')->assertRedirect();

    $order = Order::query()->sole();
    expect($order->amount_sen)->toBe(7920)
        ->and($order->discount_sen)->toBe(1980)
        ->and($order->coupon_id)->toBe($coupon->id);

    $this->get("/pesanan/{$order->reference}")->assertSee('Diskaun kod RAYA20')->assertSee('RM79.20');
});

it('takes a fixed amount off and never brings the price below RM1', function () {
    Coupon::factory()->create(['code' => 'POTONG10', 'type' => CouponType::Fixed, 'value' => 1000]);
    buyPass($this, 'POTONG10');
    expect(Order::query()->sole()->amount_sen)->toBe(8900);

    expect(Coupon::factory()->make(['type' => CouponType::Fixed, 'value' => 50000])->discountSen(9900))->toBe(9800)
        ->and(Coupon::factory()->make(['value' => 100])->discountSen(9900))->toBe(9800);
});

it('refuses coupons that are off, early, late, used up, for another product or already used', function (array $coupon, string $message, bool $usedBefore = false) {
    $created = Coupon::factory()->create(['code' => 'UJI'] + $coupon);

    if ($usedBefore) {
        Order::factory()->paid()->create(['user_id' => $this->buyer->id, 'coupon_id' => $created->id]);
    }

    buyPass($this, 'UJI')->assertSessionHasErrors(['promo_code' => $message]);
})->with([
    'inactive' => [['is_active' => false], 'Kod promosi ini tidak lagi aktif.'],
    'not started' => [['starts_at' => now()->addDay()], 'Kod promosi ini belum bermula.'],
    'ended' => [['ends_at' => now()->subMinute()], 'Kod promosi ini sudah tamat tempoh.'],
    'other product' => [['applies_to' => PromoProduct::Book], 'Kod promosi ini tidak boleh digunakan untuk produk ini.'],
    'used up' => [['max_uses' => 1, 'once_per_customer' => false], 'Kod promosi ini sudah habis digunakan.', true],
    'once each' => [[], 'Anda sudah menggunakan kod promosi ini.', true],
]);

it('counts only paid orders towards the limit', function () {
    $coupon = Coupon::factory()->create(['code' => 'SATU', 'max_uses' => 1]);
    Order::factory()->create(['coupon_id' => $coupon->id, 'status' => OrderStatus::Pending]);

    buyPass($this, 'SATU')->assertSessionHasNoErrors();
});

it('works on single books when the coupon allows it', function () {
    Setting::set('catalog_open', true);
    Coupon::factory()->create(['code' => 'BUKU', 'applies_to' => PromoProduct::Book, 'value' => 50]);
    $book = Book::factory()->published()->create(['single_price_sen' => 1500]);

    $this->actingAs($this->buyer)->post("/beli/buku/{$book->slug}", ['payment_method' => 'chip', 'promo_code' => 'BUKU']);

    expect(Order::query()->sole()->amount_sen)->toBe(750);
});

it('rejects an unknown code', function () {
    buyPass($this, 'TIADA')->assertSessionHasErrors(['promo_code' => 'Kod promosi tidak sah. Semak ejaan kod.']);
});

it('lets a Super Admin create a coupon with an amount in RM', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageCoupons::class)
        ->callAction('create', data: ['code' => 'merdeka', 'type' => 'fixed', 'value' => '5.50', 'applies_to' => 'all', 'once_per_customer' => true, 'is_active' => true])
        ->assertHasNoActionErrors();

    expect(Coupon::query()->sole())->code->toBe('MERDEKA')->value->toBe(550);
    $this->get('/admin/kupon')->assertOk()->assertSee('MERDEKA')->assertSee('RM5.50');

    $this->actingAs(User::factory()->role(Role::Editor)->create())->get('/admin/kupon')->assertForbidden();
});
