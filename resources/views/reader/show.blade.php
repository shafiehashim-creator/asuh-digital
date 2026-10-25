@php
    // Free preview (PreviewController): the first pages only, with a call to read the rest.
    $preview ??= null;
    $closeUrl ??= route('catalog.show', $book);
    $progress ??= null;
@endphp

<!DOCTYPE html>
<html lang="ms">
    <head>
        <meta charset="utf-8">
        {{-- The reader has its own zoom buttons; page zoom from quick taps only gets in the way. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        @include('partials.app-meta')
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $book->title }} · ASUH Digital</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/reader.js'])
    </head>
    <body class="touch-manipulation bg-lavender font-sans text-ink antialiased select-none">
        <div id="reader" class="flex h-dvh flex-col"
            data-src="{{ $fileUrl }}" data-book="{{ $book->slug }}" data-watermark="{{ $watermark }}"
            @if ($pageImages) data-pages="{{ json_encode($pageImages) }}" @endif
            @if ($preview) data-preview @endif
            @if ($progress) data-progress-url="{{ $progress['url'] }}" data-csrf="{{ csrf_token() }}" @if ($progress['startPage']) data-start-page="{{ $progress['startPage'] }}" @endif @endif>

            <header class="flex items-center gap-2 bg-plum px-3 pt-[calc(0.5rem+env(safe-area-inset-top))] pb-2 text-white">
                <a href="{{ $closeUrl }}" class="inline-flex min-h-11 items-center rounded-full px-3 font-bold hover:bg-white/10" aria-label="Tutup pembaca">
                    &larr; <span class="ml-1 hidden sm:inline">Tutup</span>
                </a>
                <p class="min-w-0 flex-1 truncate font-display text-lg font-bold">{{ $book->title }}</p>
                @if ($preview)
                    <span class="hidden shrink-0 rounded-full bg-kuning px-3 py-1 text-xs font-extrabold text-ink sm:inline">Pratonton {{ $preview['pages'] }} halaman</span>
                @endif
                <button type="button" data-mode-toggle hidden class="min-h-11 rounded-full border-2 border-white/40 px-3 text-sm font-bold hover:bg-white/10">Mod biasa</button>
                <button type="button" data-zoom-out class="min-h-11 min-w-11 rounded-full font-display text-xl font-bold hover:bg-white/10" aria-label="Kecilkan">−</button>
                <button type="button" data-zoom-in class="min-h-11 min-w-11 rounded-full font-display text-xl font-bold hover:bg-white/10" aria-label="Besarkan">+</button>
            </header>

            <main data-stage class="flex-1 overflow-auto px-2 py-4">
                <div data-status role="status" class="mx-auto mt-8 w-40 text-center font-semibold sm:mt-16 sm:w-52">
                    <x-book-cover :book="$book" class="animate-pulse" />
                    <p class="mt-4">Membuka buku…</p>
                </div>
                <canvas data-canvas hidden class="mx-auto block rounded-lg bg-white shadow-[0_4px_0_var(--color-ink)]"></canvas>
                <div data-flip hidden class="h-full items-center [&:not([hidden])]:flex"></div>
            </main>

            @if ($preview)
                {{-- Shown once the last preview page is on screen. --}}
                <div data-preview-cta hidden class="border-t-2 border-ink bg-kuning px-4 py-4 text-center">
                    <p class="font-display text-lg font-extrabold">Pratonton tamat. Suka buku ini?</p>
                    <p class="text-sm font-semibold text-ink/80">Baca kesemua {{ $preview['total'] }} halaman, dan semua buku ASUH Digital, dengan Pas Tahunan.</p>
                    <div class="mt-3 flex flex-wrap justify-center gap-2">
                        @if ($preview['offer']->available())
                            <a href="{{ route('pass.show') }}" class="btn-gelap">Dapatkan Pas Tahunan</a>
                        @endif
                        @if ($book->single_price_sen)
                            <a href="{{ route('purchase.book', $book) }}" class="btn-utama">Beli buku ini {{ App\Support\Money::short($book->single_price_sen) }}</a>
                        @endif
                        @guest
                            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center px-3 font-bold underline">Sudah ada Pas? Log masuk</a>
                        @endguest
                    </div>
                </div>
            @endif

            <nav class="flex items-center justify-between gap-3 border-t-2 border-ink/10 bg-white px-3 pt-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))]" aria-label="Halaman">
                <button type="button" data-prev class="btn-gelap px-4 disabled:opacity-40 sm:px-5" aria-label="Halaman sebelum" disabled>&larr;<span class="ml-1 hidden sm:inline">Sebelum</span></button>
                <p data-page aria-live="polite" class="text-center text-sm font-bold whitespace-nowrap"></p>
                <button type="button" data-next class="btn-utama px-4 disabled:opacity-40 sm:px-5" aria-label="Halaman seterusnya" disabled><span class="mr-1 hidden sm:inline">Seterus</span>&rarr;</button>
            </nav>
        </div>
    </body>
</html>
