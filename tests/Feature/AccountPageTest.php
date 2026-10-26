<?php

use App\Actions\Fortify\CreateNewUser;
use App\Enums\Role;
use App\Models\Book;
use App\Models\Child;
use App\Models\Order;
use App\Models\Pass;
use App\Models\ReadingSession;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('sends guests to the login page', function () {
    $this->get('/akaun')->assertRedirect('/login');
});

it('shows an empty account with a call to get the pass', function () {
    $this->actingAs(User::factory()->create(['name' => 'Aminah']))
        ->get('/akaun')
        ->assertOk()
        ->assertSee('Assalamualaikum, Aminah')
        ->assertSee('Anda belum melanggan')
        ->assertSee('Langgan sekarang');

    $this->get('/akaun/langganan')
        ->assertOk()
        ->assertSee('Anda belum mempunyai langganan yang aktif.')
        ->assertSee('Tiada pesanan lagi.');
});

it('shows an active pass and the order', function () {
    $order = Order::factory()->paid()->create();
    Pass::query()->create([
        'user_id' => $order->user_id,
        'plan_id' => $order->plan_id,
        'order_id' => $order->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->setDate(2027, 10, 24),
    ]);

    $this->actingAs($order->user)
        ->get('/akaun/langganan')
        ->assertOk()
        ->assertSee('Aktif')
        ->assertSee('24 Oktober 2027')
        ->assertSee($order->reference)
        ->assertSee('RM79.00')
        ->assertSee('Dibayar');
});

it('explains a paid pass that waits for the launch date', function () {
    $order = Order::factory()->paid()->create();
    Pass::query()->create([
        'user_id' => $order->user_id,
        'plan_id' => $order->plan_id,
        'order_id' => $order->id,
    ]);

    $this->actingAs($order->user)
        ->get('/akaun/langganan')
        ->assertSee('bermula pada tarikh platform ASUH Digital dilancarkan');
});

it('only shows the signed-in parent their own orders', function () {
    $mine = Order::factory()->create();
    $theirs = Order::factory()->create();

    $this->actingAs($mine->user)
        ->get('/akaun/langganan')
        ->assertSee($mine->reference)
        ->assertDontSee($theirs->reference);
});

it('updates the profile and normalises the phone number', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'Nama Baharu',
            'email' => $user->email,
            'phone' => '+60 12-345 6789',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh())
        ->name->toBe('Nama Baharu')
        ->phone->toBe('0123456789');
});

it('rejects an invalid phone number', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '12345',
        ])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'phone');
});

it('creates parent accounts with the parent role and requires PDPA consent', function () {
    $input = [
        'name' => 'Ibu Contoh',
        'email' => 'ibu@example.com',
        'phone' => '012 345 6789',
        'password' => 'rahsia123',
        'password_confirmation' => 'rahsia123',
    ];

    expect(fn () => app(CreateNewUser::class)->create($input))
        ->toThrow(ValidationException::class);

    $user = app(CreateNewUser::class)->create($input + ['pdpa_consent' => '1'])->refresh();

    expect($user->role)->toBe(Role::Parent)
        ->and($user->phone)->toBe('0123456789')
        ->and($user->hasVerifiedEmail())->toBeFalse();
});

it('summarises the family with stat cards, continue reading and children', function () {
    Setting::set('catalog_open', true);
    $order = Order::factory()->paid()->create();
    $parent = $order->user;
    Pass::query()->create(['user_id' => $parent->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
    $child = Child::factory()->for($parent)->create(['nickname' => 'Aisyah']);
    // 12 pages, stopped at page 3: a quarter read.
    $book = Book::factory()->published()->withFile()->create(['title' => 'Kisah Semut', 'age_group' => $child->age_group]);
    ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => $book->id, 'read_on' => today(), 'minutes' => 12, 'last_page' => 3]);

    $this->actingAs($parent)->get('/akaun')
        ->assertOk()
        ->assertSee('12 minit membaca minggu ini')
        ->assertSee('Mod Anak: Aisyah')
        ->assertSee('Kisah Semut')
        ->assertSee('25%')
        ->assertSee(route('account.progress'));

    $this->get('/akaun/pencapaian')->assertOk()->assertSee('Aisyah')->assertSee('Kuiz Pertama');
    $this->get('/akaun/anak')->assertOk()->assertSee('Aisyah')->assertSee('PIN ibu bapa');
    $this->get('/akaun/profil')->assertOk()->assertSee('Maklumat peribadi')->assertSee('Laporan bacaan mingguan');
});

it('opens the book straight away when continuing in Mod Anak', function () {
    Setting::set('catalog_open', true);
    $order = Order::factory()->paid()->create();
    Pass::query()->create(['user_id' => $order->user_id, 'plan_id' => $order->plan_id, 'order_id' => $order->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
    $child = Child::factory()->for($order->user)->create();
    $book = Book::factory()->published()->create(['age_group' => $child->age_group]);

    $this->actingAs($order->user)
        ->post(route('children.child-mode', $child), ['buku' => $book->slug])
        ->assertRedirect(route('reader.show', $book));
});
