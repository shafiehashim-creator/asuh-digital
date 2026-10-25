<?php

use App\Enums\Role;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Passes\Pages\ListPasses;
use App\Filament\Widgets\MostReadBooks;
use App\Models\Book;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Pass;
use App\Models\User;
use Livewire\Livewire;

function adminPass(User $user, ?string $startsAt, ?string $endsAt): Pass
{
    $order = Order::factory()->paid()->create(['user_id' => $user->id]);

    return Pass::query()->create([
        'user_id' => $user->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
        'starts_at' => $startsAt ? now()->modify($startsAt) : null,
        'ends_at' => $endsAt ? now()->modify($endsAt) : null,
    ]);
}

it('keeps customers and passes away from editors and parents', function (string $url) {
    $this->actingAs(User::factory()->role(Role::Editor)->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
})->with(['/admin/pelanggan', '/admin/langganan']);

it('lists only parent accounts as customers', function () {
    $admin = User::factory()->admin()->create(['name' => 'Pentadbir Utama']);
    $parent = User::factory()->create(['name' => 'Ibu Salmah']);
    $this->actingAs($admin);

    $this->get('/admin/pelanggan')->assertOk();

    Livewire::test(ListCustomers::class)
        ->assertCanSeeTableRecords([$parent])
        ->assertCanNotSeeTableRecords([$admin]);
});

it('finds customers by e-mail and by active pass', function () {
    $this->actingAs(User::factory()->admin()->create());
    $withPass = User::factory()->create(['email' => 'aktif@example.com']);
    adminPass($withPass, '-1 day', '+1 year');
    $without = User::factory()->create(['email' => 'tiada@example.com']);

    Livewire::test(ListCustomers::class)
        ->searchTable('aktif@')
        ->assertCanSeeTableRecords([$withPass])
        ->assertCanNotSeeTableRecords([$without]);

    Livewire::test(ListCustomers::class)
        ->filterTable('active_pass')
        ->assertCanSeeTableRecords([$withPass])
        ->assertCanNotSeeTableRecords([$without]);
});

it('shows a customer with their passes, books and orders', function () {
    $this->actingAs(User::factory()->admin()->create());
    $parent = User::factory()->create();
    $pass = adminPass($parent, '-1 day', '+1 year');
    $book = Book::factory()->published()->create(['title' => 'Doa Sebelum Tidur']);
    Entitlement::query()->create(['user_id' => $parent->id, 'book_id' => $book->id]);

    $this->get("/admin/pelanggan/{$parent->id}")
        ->assertOk()
        ->assertSee($parent->email)
        ->assertSee('Aktif')
        ->assertSee('Doa Sebelum Tidur')
        ->assertSee($pass->order->reference);
});

it('does not open staff accounts as customers', function () {
    $this->actingAs($admin = User::factory()->admin()->create());

    $this->get("/admin/pelanggan/{$admin->id}")->assertNotFound();
});

it('filters passes by state', function () {
    $this->actingAs(User::factory()->admin()->create());
    $active = adminPass(User::factory()->create(), '-1 day', '+6 months');
    $expiring = adminPass(User::factory()->create(), '-11 months', '+10 days');
    $waiting = adminPass(User::factory()->create(), null, null);
    $ended = adminPass(User::factory()->create(), '-13 months', '-1 month');

    Livewire::test(ListPasses::class)
        ->assertCanSeeTableRecords([$active, $expiring, $waiting, $ended])
        ->filterTable('state', 'expiring')
        ->assertCanSeeTableRecords([$expiring])
        ->assertCanNotSeeTableRecords([$active, $waiting, $ended]);

    Livewire::test(ListPasses::class)
        ->filterTable('state', 'waiting')
        ->assertCanSeeTableRecords([$waiting])
        ->assertCanNotSeeTableRecords([$active, $expiring, $ended]);

    Livewire::test(ListPasses::class)
        ->filterTable('state', 'ended')
        ->assertCanSeeTableRecords([$ended])
        ->assertCanNotSeeTableRecords([$active, $expiring, $waiting]);
});

it('ranks the most-read books for staff, editors included', function () {
    $quiet = Book::factory()->published()->create(['title' => 'Belum Dibaca']);
    $popular = Book::factory()->published()->create(['title' => 'Paling Digemari']);
    $some = Book::factory()->published()->create(['title' => 'Sederhana']);
    foreach (User::factory()->count(3)->create() as $parent) {
        $popular->recordReader($parent);
    }
    $some->recordReader(User::factory()->create());

    $this->actingAs(User::factory()->role(Role::Editor)->create());

    expect(MostReadBooks::canView())->toBeTrue();
    Livewire::test(MostReadBooks::class)
        ->assertCanSeeTableRecords([$popular, $some], inOrder: true)
        ->assertCanNotSeeTableRecords([$quiet]);
});

it('renders the dashboard with the reading widget for admins and editors', function (Role $role) {
    $this->actingAs(User::factory()->role($role)->create())
        ->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(MostReadBooks::class);
})->with([Role::Admin, Role::Editor]);
