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
                    @foreach ($book->categories as $category)
                        <a href="{{ route('catalog.index', ['kategori' => $category->slug]) }}" class="rounded-full bg-biru/10 px-3 py-1 text-biru hover:bg-biru/20">{{ $category->name }}</a>
                    @endforeach
                    <span class="rounded-full bg-kuning px-3 py-1">✓ Disemak syariah</span>
                </p>
                <h1 class="mt-4 font-display text-3xl font-extrabold text-magenta sm:text-4xl">{{ $book->title }}</h1>
                <p class="mt-1 text-lg text-ink/80">oleh {{ $book->author }}</p>

                @if ($book->readers_count >= App\Services\CatalogStats::MIN_READERS)
                    <p class="mt-3 inline-flex items-center gap-1 rounded-full bg-oren/15 px-3 py-1 text-sm font-bold">🔥 {{ number_format($book->readers_count) }} keluarga telah membaca buku ini</p>
                @endif

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
                        @if ($previewPages > 0)
                            <a href="{{ route('catalog.preview', $book) }}" class="btn-gelap w-full">📖 Baca pratonton percuma ({{ $previewPages }} halaman)</a>
                        @endif

                        @if ($offer->available())
                            <div @class(['rounded-2xl border-2 border-ink bg-kuning p-4', 'mt-4' => $previewPages > 0])>
                                <p class="text-xs font-extrabold tracking-wide uppercase">★ Paling jimat · Pas Tahunan</p>
                                <p class="mt-1 font-display text-3xl font-extrabold">
                                    {{ Money::short($offer->priceSen()) }}
                                    @if ($offer->compareAtSen())
                                        <span class="text-lg text-ink/60 line-through">{{ Money::short($offer->compareAtSen()) }}</span>
                                    @endif
                                </p>
                                <p class="text-sm font-semibold">
                                    Buku ini @if ($bookCount > 1) dan {{ $bookCount - 1 }} buku lain @endif untuk seisi keluarga, selama 12 bulan.
                                    @if ($bookCount > 1) Kira-kira {{ Money::format(intdiv($offer->priceSen(), $bookCount)) }} sebuku. @endif
                                </p>
                                <a href="{{ $offer->checkoutUrl() }}" class="btn-gelap mt-3 w-full">{{ $offer->ctaLabel() }}</a>
                                <a href="{{ route('pass.show') }}" class="mt-2 block text-center text-sm font-bold underline">Apa yang termasuk?</a>
                            </div>
                        @endif

                        @if ($book->single_price_sen)
                            <a href="{{ route('purchase.book', $book) }}" class="btn-utama mt-3 w-full">Beli buku ini {{ Money::short($book->single_price_sen) }}</a>
                        @endif

                        @guest
                            <p class="mt-3 text-center text-sm">Sudah ada Pas? <a href="{{ route('login') }}" class="pautan font-bold">Log masuk</a></p>
                        @endguest

                        <ul class="mt-4 grid gap-1 text-sm font-semibold text-ink/80 sm:grid-cols-2">
                            <li>✓ Disemak syariah</li>
                            <li>✓ Baca di telefon, tablet & komputer</li>
                            <li>✓ Akses serta-merta selepas bayar</li>
                            <li>✓ Tiada caj automatik</li>
                        </ul>
                        <p class="mt-3 text-center text-xs font-bold text-ink/60">FPX · Kad debit/kredit · e-Wallet · Pindahan bank</p>
                    @endif
                </div>
            </div>
        </div>

        @if ($recommended->isNotEmpty())
            <section class="mt-16" aria-labelledby="recommended-heading">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 id="recommended-heading" class="font-display text-2xl font-bold text-plum">Anda mungkin suka juga</h2>
                    <a href="{{ route('catalog.index') }}" class="pautan text-sm">Lihat semua buku</a>
                </div>

                @unless ($hasPass)
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-ink bg-kuning px-5 py-4">
                        <p class="font-bold">Semua buku ini termasuk dalam Pas Tahunan. Satu bayaran, seisi keluarga baca sepanjang tahun.</p>
                        <a href="{{ route('pass.show') }}" class="btn-gelap">Dapatkan Pas Tahunan</a>
                    </div>
                @endunless

                {{-- A swipeable row on phones, a grid on larger screens. --}}
                <ul class="-mx-4 mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-4 sm:overflow-visible sm:px-0">
                    @foreach ($recommended as $other)
                        <li class="w-36 shrink-0 snap-start sm:w-auto">
                            <a href="{{ route('catalog.show', $other) }}" class="group block">
                                <x-book-cover :book="$other" class="transition group-hover:-translate-y-1" />
                                <p class="mt-2 font-display font-bold leading-tight group-hover:text-plum">{{ $other->title }}</p>
                                <p class="mt-1 text-xs font-bold">
                                    @if ($other->readable)
                                        <span class="text-biru">✓ Boleh dibaca</span>
                                    @elseif ($other->single_price_sen)
                                        <span class="text-magenta">{{ Money::short($other->single_price_sen) }}</span> <span class="text-ink/60">atau dengan Pas</span>
                                    @else
                                        <span class="text-ink/60">Dengan Pas Tahunan</span>
                                    @endif
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
