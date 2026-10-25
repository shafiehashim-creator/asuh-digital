<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Mail\OrderReceipt;
use App\Models\Book;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\User;
use App\Services\DashboardStats;
use App\Services\ManualGrants;
use Database\Seeders\PlanSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Mail::fake();
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
});

it('adds a subscription to a customer from their admin page', function () {
    $customer = User::factory()->create();
    $plan = Plan::findByCode(Plan::PAS_TAHUNAN);
    $this->actingAs($this->admin);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('grantPlan', data: ['plan_id' => $plan->id, 'amount' => '50', 'note' => 'Tunai di booth PBAKL', 'send_receipt' => true])
        ->assertHasNoActionErrors();

    $order = Order::query()->sole();
    expect($order->payment_method)->toBe(PaymentMethod::Manual)
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->amount_sen)->toBe(5000)
        ->and($order->admin_note)->toBe('Tunai di booth PBAKL')
        ->and($order->confirmed_by)->toBe($this->admin->id)
        ->and($customer->activePass()?->plan_id)->toBe($plan->id);
    Mail::assertQueued(OrderReceipt::class, fn ($mail) => $mail->hasTo($customer->email));

    // Counted in sales.
    expect(app(DashboardStats::class)->sales()['today'])->toBe(5000);
});

it('adds a single e-book for good, optionally without a receipt', function () {
    $customer = User::factory()->create();
    $book = Book::factory()->published()->create(['title' => 'Kisah Semut']);
    $this->actingAs($this->admin);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('grantBook', data: ['book_id' => $book->id, 'amount' => '0', 'note' => 'Hadiah peraduan', 'send_receipt' => false])
        ->assertHasNoActionErrors();

    expect($customer->entitlements()->where('book_id', $book->id)->exists())->toBeTrue()
        ->and($customer->canReadBook($book))->toBeTrue()
        ->and(Order::query()->sole()->amount_sen)->toBe(0);
    Mail::assertNothingOutgoing();
});

it('starts a new subscription after the current one ends', function () {
    $customer = User::factory()->create();
    $plan = Plan::findByCode(Plan::PAS_TAHUNAN);
    $order = Order::factory()->paid()->create(['user_id' => $customer->id, 'plan_id' => $plan->id]);
    $current = Pass::query()->create([
        'user_id' => $customer->id, 'plan_id' => $plan->id, 'order_id' => $order->id,
        'starts_at' => now()->subMonth(), 'ends_at' => now()->addMonths(11),
    ]);

    app(ManualGrants::class)->plan($customer, $plan, 0, 'Pampasan', $this->admin, false);

    $added = $customer->passes()->latest('id')->first();
    expect($added->starts_at->toDateTimeString())->toBe($current->ends_at->toDateTimeString());
});

it('does not give the same book twice', function () {
    $customer = User::factory()->create();
    $book = Book::factory()->published()->create();
    app(ManualGrants::class)->book($customer, $book, 0, 'Hadiah', $this->admin, false);

    expect(fn () => app(ManualGrants::class)->book($customer, $book, 0, 'Lagi', $this->admin, false))
        ->toThrow(InvalidArgumentException::class);
});

it('needs a reason', function () {
    $customer = User::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('grantPlan', data: ['plan_id' => Plan::findByCode(Plan::PAS_TAHUNAN)->id, 'amount' => '0', 'note' => ''])
        ->assertHasActionErrors(['note']);

    expect(Order::query()->count())->toBe(0);
});

it('lets only the super admin give things away', function () {
    $editor = User::factory()->role(Role::Editor)->create();

    expect(fn () => app(ManualGrants::class)->plan(User::factory()->create(), Plan::findByCode(Plan::PAS_TAHUNAN), 0, 'X', $editor, false))
        ->toThrow(InvalidArgumentException::class);
    $this->actingAs($editor)->get('/admin/pelanggan')->assertForbidden();
});

it('keeps the manual method away from checkout', function () {
    $this->post('/pre-order', ['payment_method' => 'manual'])->assertSessionHasErrors('payment_method');
});

it('creates a customer who gets an e-mail to set a password', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListCustomers::class)
        ->callAction('createCustomer', data: ['name' => 'Encik Booth', 'email' => 'Booth@Example.com', 'phone' => '012-345 6789'])
        ->assertHasNoActionErrors();

    $customer = User::query()->where('email', 'booth@example.com')->sole();
    expect($customer->refresh()->role)->toBe(Role::Parent)
        ->and($customer->hasVerifiedEmail())->toBeTrue();
    Notification::assertSentTo($customer, ResetPassword::class);

    Livewire::test(ListCustomers::class)
        ->callAction('createCustomer', data: ['name' => 'Lagi', 'email' => 'booth@example.com', 'phone' => '012-345 6789'])
        ->assertHasActionErrors(['email']);
});

it('shows the admin note on the order', function () {
    $customer = User::factory()->create();
    $order = app(ManualGrants::class)->plan($customer, Plan::findByCode(Plan::PAS_TAHUNAN), 0, 'Hadiah peraduan', $this->admin, false);
    $this->actingAs($this->admin);

    $this->get("/admin/pesanan/{$order->id}")->assertOk()->assertSee('Hadiah peraduan')->assertSee('Manual (oleh ASUH)');
});
