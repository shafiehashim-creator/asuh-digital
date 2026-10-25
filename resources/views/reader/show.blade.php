<!DOCTYPE html>
<html lang="ms">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $book->title }} · ASUH Digital</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/reader.js'])
    </head>
    <body class="bg-lavender font-sans text-ink antialiased">
        <div id="reader" class="flex h-dvh flex-col"
            data-src="{{ $fileUrl }}" data-book="{{ $book->slug }}" data-watermark="{{ $watermark }}">

            <header class="flex items-center gap-2 bg-plum px-3 py-2 text-white">
                <a href="{{ route('catalog.show', $book) }}" class="inline-flex min-h-11 items-center rounded-full px-3 font-bold hover:bg-white/10" aria-label="Tutup pembaca">
                    &larr; <span class="ml-1 hidden sm:inline">Tutup</span>
                </a>
                <p class="min-w-0 flex-1 truncate font-display text-lg font-bold">{{ $book->title }}</p>
                <button type="button" data-mode-toggle hidden class="min-h-11 rounded-full border-2 border-white/40 px-3 text-sm font-bold hover:bg-white/10">Mod biasa</button>
                <button type="button" data-zoom-out class="min-h-11 min-w-11 rounded-full font-display text-xl font-bold hover:bg-white/10" aria-label="Kecilkan">−</button>
                <button type="button" data-zoom-in class="min-h-11 min-w-11 rounded-full font-display text-xl font-bold hover:bg-white/10" aria-label="Besarkan">+</button>
            </header>

            <main data-stage class="flex-1 overflow-auto px-2 py-4">
                <p data-status role="status" class="mt-16 text-center font-semibold">Membuka buku…</p>
                <canvas data-canvas class="mx-auto block rounded-lg bg-white shadow-[0_4px_0_var(--color-ink)]"></canvas>
                <div data-flip hidden class="h-full items-center [&:not([hidden])]:flex"></div>
            </main>

            <nav class="flex items-center justify-between gap-3 border-t-2 border-ink/10 bg-white px-3 py-2" aria-label="Halaman">
                <button type="button" data-prev class="btn-gelap px-5 disabled:opacity-40" disabled>&larr; Sebelum</button>
                <p data-page aria-live="polite" class="text-sm font-bold"></p>
                <button type="button" data-next class="btn-utama px-5 disabled:opacity-40" disabled>Seterus &rarr;</button>
            </nav>
        </div>
    </body>
</html>
