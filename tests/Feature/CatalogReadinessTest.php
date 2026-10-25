<?php

use App\Models\Book;
use App\Models\User;
use App\Services\CatalogReadiness;

it('warns when no book has been published', function () {
    Book::factory()->create();

    expect(app(CatalogReadiness::class)->problems())
        ->toBe(['Belum ada buku yang diterbitkan. Katalog akan kosong.']);
});

it('names published books missing a file, the quick version or a cover', function () {
    Book::factory()->published()->create(['title' => 'Tanpa Fail', 'cover_path' => 'covers/a.webp']);
    Book::factory()->published()->withFile()->create(['title' => 'Tanpa Versi Pantas', 'cover_path' => 'covers/b.webp']);
    $ready = Book::factory()->published()->withFile()->create(['title' => 'Sudah Sedia', 'cover_path' => 'covers/c.webp']);
    $ready->files()->first()->forceFill(['page_images' => 12, 'pages_ready_at' => now()])->save();
    $noCover = Book::factory()->published()->withFile()->create(['title' => 'Tanpa Kaver', 'cover_path' => null]);
    $noCover->files()->first()->forceFill(['page_images' => 12, 'pages_ready_at' => now()])->save();

    $problems = implode("\n", app(CatalogReadiness::class)->problems());

    expect($problems)
        ->toContain('Tiada fail PDF, jadi tidak boleh dibaca: Tanpa Fail.')
        ->toContain('Belum ada versi pantas (lambat di telefon, tiada pratonton percuma): Tanpa Versi Pantas.')
        ->toContain('Belum ada kaver: Tanpa Kaver.')
        ->not->toContain('Sudah Sedia');
});

it('is ready when every published book is complete', function () {
    $book = Book::factory()->published()->withFile()->create(['cover_path' => 'covers/a.webp']);
    $book->files()->first()->forceFill(['page_images' => 12, 'pages_ready_at' => now()])->save();

    expect(app(CatalogReadiness::class)->problems())->toBe([]);
});

it('shortens long lists of titles', function () {
    foreach (['A', 'B', 'C', 'D', 'E'] as $title) {
        Book::factory()->published()->withFile()->create(['title' => "Buku {$title}", 'cover_path' => null]);
    }

    expect(implode("\n", app(CatalogReadiness::class)->problems()))
        ->toContain('Belum ada kaver: Buku A, Buku B, Buku C dan 2 lagi.');
});

it('shows the checklist next to the catalogue switch', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/tetapan')
        ->assertOk()
        ->assertSee('Senarai semak katalog: perlu perhatian')
        ->assertSee('Belum ada buku yang diterbitkan.');
});
