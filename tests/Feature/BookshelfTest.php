<?php

use App\Models\Book;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Setting;
use App\Models\User;

function shelfPassHolder(): User
{
    $user = User::factory()->create();
    $order = Order::factory()->paid()->create(['user_id' => $user->id]);
    Pass::query()->create([
        'user_id' => $user->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
    ]);

    return $user;
}

it('hides the shelf while the catalogue is closed', function () {
    $buyer = User::factory()->create();
    $book = Book::factory()->published()->create(['title' => 'Kisah Rahsia']);
    Entitlement::query()->create(['user_id' => $buyer->id, 'book_id' => $book->id]);

    $this->actingAs($buyer)->get('/akaun')
        ->assertOk()
        ->assertDontSee('Rak buku saya')
        ->assertDontSee('Kisah Rahsia');
    $this->get('/akaun/rak')->assertNotFound();
});

it('shows bought single books with a link to read them', function () {
    Setting::set('catalog_open', true);
    $buyer = User::factory()->create();
    $book = Book::factory()->published()->create(['title' => 'Adab Makan']);
    Entitlement::query()->create(['user_id' => $buyer->id, 'book_id' => $book->id]);

    $this->actingAs($buyer)->get('/akaun/rak')
        ->assertOk()
        ->assertSee('Rak buku saya')
        ->assertSee('Buku yang dibeli')
        ->assertSee('Adab Makan')
        ->assertSee(route('reader.show', $book), false);
});

it('lists books opened lately, newest first, for pass holders', function () {
    Setting::set('catalog_open', true);
    $reader = shelfPassHolder();
    $older = Book::factory()->published()->create(['title' => 'Buku Lama Dibaca']);
    $newer = Book::factory()->published()->create(['title' => 'Buku Baru Dibaca']);

    $this->travel(-2)->days();
    $older->recordReader($reader);
    $this->travelBack();
    $newer->recordReader($reader);

    $this->actingAs($reader)->get('/akaun/rak')
        ->assertOk()
        ->assertSee('membuka <strong>semua buku</strong>', false)
        ->assertSee('Sambung membaca')
        ->assertSeeInOrder(['Buku Baru Dibaca', 'Buku Lama Dibaca']);
});

it('drops pass books from the shelf once the pass ends but keeps bought ones', function () {
    Setting::set('catalog_open', true);
    $reader = shelfPassHolder();
    $passBook = Book::factory()->published()->create(['title' => 'Buku Dalam Pas']);
    $owned = Book::factory()->published()->create(['title' => 'Buku Milik Sendiri']);
    Entitlement::query()->create(['user_id' => $reader->id, 'book_id' => $owned->id]);
    $passBook->recordReader($reader);
    $owned->recordReader($reader);

    $reader->passes()->update(['ends_at' => now()->subMinute()]);

    $this->actingAs($reader)->get('/akaun/rak')
        ->assertOk()
        ->assertDontSee('Buku Dalam Pas')
        ->assertSee('Buku Milik Sendiri');
});

it('leaves unpublished books off the shelf', function () {
    Setting::set('catalog_open', true);
    $buyer = User::factory()->create();
    $book = Book::factory()->create(['title' => 'Draf Belum Terbit']);
    Entitlement::query()->create(['user_id' => $buyer->id, 'book_id' => $book->id]);

    $this->actingAs($buyer)->get('/akaun/rak')
        ->assertOk()
        ->assertDontSee('Draf Belum Terbit')
        ->assertSee('Rak anda masih kosong.');
});
