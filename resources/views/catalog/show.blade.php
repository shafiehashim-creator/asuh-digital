@php
    use App\Support\Money;

    $file = $book->readableFile();
@endphp

<x-layouts.app :title="$book->title">
    <div class="mx-auto max-w-5xl px-4 py-10">
        <x-catalog-preview-notice />

        @unless ($book->isPublished())
            <div role="note" class="mb-6 rounded-xl border-2 border-ink/30 bg-ink/5 px-4 py-3 text-sm font-semibold">
                Buku ini belum diterbitkan ({{ $book->status->getLabel() }}). Hanya staf boleh melihatnya.
            </div>
        @endunless

        @if (session('reader_denied'))
            <div role="alert" class="mb-6 rounded-xl border-2 border-magenta bg-magenta/5 px-4 py-3 font-semibold text-magenta">
                Anda perlukan Pas Tahunan yang aktif untuk membaca buku ini.
            </div>
        @endif

        <a href="{{ route('catalog.index') }}" class="pautan text-sm">&larr; Kembali ke katalog</a>

        <div class="mt-6 grid gap-8 md:grid-cols-[280px_1fr]">
            <x-book-cover :book="$book" size="lg" class="mx-auto max-w-[280px]" />

            <div>
                <p class="flex flex-wrap gap-2 text-sm font-bold">
                    <span class="rounded-full bg-lavender px-3 py-1">{{ $book->age_group->getLabel() }}</span>
                    <a href="{{ route('catalog.index', ['kategori' => $book->category->slug]) }}" class="rounded-full bg-biru/10 px-3 py-1 text-biru hover:bg-biru/20">{{ $book->category->name }}</a>
                    <span class="rounded-full bg-kuning px-3 py-1">✓ Disemak syariah</span>
                </p>
                <h1 class="mt-4 font-display text-3xl font-extrabold text-magenta sm:text-4xl">{{ $book->title }}</h1>
                <p class="mt-1 text-lg text-ink/80">oleh {{ $book->author }}</p>

                <div class="mt-6 space-y-3 text-ink/90">
                    @foreach (preg_split('/\n\s*\n/', trim($book->synopsis)) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>

                @if ($file?->page_count)
                    <p class="mt-4 text-sm text-ink/70">{{ $file->page_count }} halaman</p>
                @endif

                <div class="mt-8 rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                    @if ($canRead && $file)
                        <a href="{{ route('reader.show', $book) }}" class="btn-utama w-full text-lg">Baca sekarang</a>
                    @elseif ($canRead)
                        <p class="font-semibold">Fail buku ini sedang disediakan.</p>
                    @else
                        <p class="font-display text-lg font-bold text-plum">Baca dengan Pas Tahunan</p>
                        <p class="mt-1 text-ink/80">Satu Pas untuk seisi keluarga: semua e-book ASUH Digital selama 12 bulan.</p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <a href="{{ route('purchase.pass') }}" class="btn-utama">Dapatkan Pas Tahunan</a>
                            @if ($book->single_price_sen)
                                <a href="{{ route('purchase.book', $book) }}" class="btn-gelap">Beli buku ini {{ Money::short($book->single_price_sen) }}</a>
                            @endif
                            @guest
                                <a href="{{ route('login') }}" class="btn-gelap">Log masuk</a>
                            @endguest
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-16" aria-labelledby="related-heading">
                <h2 id="related-heading" class="font-display text-2xl font-bold text-plum">Buku lain dalam {{ $book->category->name }}</h2>
                <ul class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($related as $other)
                        <li>
                            <a href="{{ route('catalog.show', $other) }}" class="group block">
                                <x-book-cover :book="$other" />
                                <p class="mt-2 font-display font-bold leading-tight group-hover:text-plum">{{ $other->title }}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
