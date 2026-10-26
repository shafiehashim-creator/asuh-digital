@php
    use App\Support\Money;

    $file = $book->readableFile();
@endphp

<x-layouts.app :title="$book->localTitle()">
    <div class="mx-auto max-w-5xl px-4 py-10">
        <x-catalog-preview-notice />

        @unless ($book->isPublished())
            <div role="note" class="mb-6 rounded-xl border-2 border-ink/30 bg-ink/5 px-4 py-3 text-sm font-semibold">
                {{ __('Buku ini belum diterbitkan (:status). Hanya staf boleh melihatnya.', ['status' => $book->status->getLabel()]) }}
            </div>
        @endunless

        @if (session('reader_denied'))
            <div role="alert" class="mb-6 rounded-xl border-2 border-magenta bg-magenta/5 px-4 py-3 font-semibold text-magenta">
                {{ __('Anda perlukan Langganan Tahunan yang aktif untuk membaca buku ini.') }}
            </div>
        @endif

        <a href="{{ route('catalog.index') }}" class="pautan text-sm">&larr; {{ __('Kembali ke katalog') }}</a>

        <div class="mt-6 grid gap-8 md:grid-cols-[280px_1fr]">
            <x-book-cover :book="$book" size="lg" class="mx-auto max-w-[280px]" />

            <div>
                <p class="flex flex-wrap gap-2 text-sm font-bold">
                    <span class="rounded-full bg-lavender px-3 py-1">{{ $book->age_group->getLabel() }}</span>
                    @foreach ($book->categories as $category)
                        <a href="{{ route('catalog.index', ['kategori' => $category->slug]) }}" class="rounded-full bg-biru/10 px-3 py-1 text-biru hover:bg-biru/20">{{ __($category->name) }}</a>
                    @endforeach
                    <span class="rounded-full bg-kuning px-3 py-1">✓ {{ __('Disemak syariah') }}</span>
                </p>
                <h1 class="mt-4 font-display text-3xl font-extrabold text-magenta sm:text-4xl">{{ $book->localTitle() }}</h1>
                <p class="mt-1 text-lg text-ink/80">{{ __('oleh :author', ['author' => $book->author]) }}</p>

                @if ($book->readers_count >= App\Services\CatalogStats::MIN_READERS)
                    <p class="mt-3 inline-flex items-center gap-1 rounded-full bg-oren/15 px-3 py-1 text-sm font-bold">🔥 {{ __(':count keluarga telah membaca buku ini', ['count' => number_format($book->readers_count)]) }}</p>
                @endif

                <div class="mt-6 space-y-3 text-ink/90">
                    @foreach (preg_split('/\n\s*\n/', trim($book->localSynopsis())) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>

                @if ($file?->page_count)
                    <p class="mt-4 text-sm text-ink/70">{{ __(':count halaman', ['count' => $file->page_count]) }}</p>
                @endif

                <div class="mt-8 rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                    @if ($canRead && $file)
                        <a href="{{ route('reader.show', $book) }}" class="btn-utama w-full text-lg">{{ __('Baca sekarang') }}</a>
                    @elseif ($canRead)
                        <p class="font-semibold">{{ __('Fail buku ini sedang disediakan.') }}</p>
                    @else
                        @if ($previewPages > 0)
                            <a href="{{ route('catalog.preview', $book) }}" class="btn-gelap w-full">📖 {{ __('Baca pratonton percuma (:count halaman)', ['count' => $previewPages]) }}</a>
                        @endif

                        @if ($offer->available())
                            <div @class(['rounded-2xl border-2 border-ink bg-kuning p-4', 'mt-4' => $previewPages > 0])>
                                <p class="text-xs font-extrabold tracking-wide uppercase">★ {{ __('Paling jimat · Langganan Tahunan') }}</p>
                                <p class="mt-1 font-display text-3xl font-extrabold">
                                    {{ Money::short($offer->priceSen()) }}
                                    @if ($offer->compareAtSen())
                                        <span class="text-lg text-ink/60 line-through">{{ Money::short($offer->compareAtSen()) }}</span>
                                    @endif
                                </p>
                                <p class="text-sm font-semibold">
                                    {{ $bookCount > 1
                                        ? __('Buku ini dan :count buku lain untuk seisi keluarga, selama 12 bulan.', ['count' => $bookCount - 1])
                                        : __('Buku ini untuk seisi keluarga, selama 12 bulan.') }}
                                    @if ($bookCount > 1) {{ __('Kira-kira :price sebuku.', ['price' => Money::format(intdiv($offer->priceSen(), $bookCount))]) }} @endif
                                </p>
                                <a href="{{ $offer->checkoutUrl() }}" class="btn-gelap mt-3 w-full">{{ $offer->ctaLabel() }}</a>
                                <a href="{{ route('pass.show') }}" class="mt-2 block text-center text-sm font-bold underline">{{ __('Apa yang termasuk?') }}</a>
                            </div>
                        @endif

                        @if ($book->single_price_sen)
                            <a href="{{ route('purchase.book', $book) }}" class="btn-utama mt-3 w-full">{{ __('Beli buku ini :price', ['price' => Money::short($book->single_price_sen)]) }}</a>
                        @endif

                        @guest
                            <p class="mt-3 text-center text-sm">{{ __('Sudah ada langganan?') }} <a href="{{ route('login') }}" class="pautan font-bold">{{ __('Log masuk') }}</a></p>
                        @endguest

                        <ul class="mt-4 grid gap-1 text-sm font-semibold text-ink/80 sm:grid-cols-2">
                            <li>✓ {{ __('Disemak syariah') }}</li>
                            <li>✓ {{ __('Baca di telefon, tablet & komputer') }}</li>
                            <li>✓ {{ __('Akses serta-merta selepas bayar') }}</li>
                            <li>✓ {{ __('Tiada caj automatik') }}</li>
                        </ul>
                        <p class="mt-3 text-center text-xs font-bold text-ink/60">{{ __('FPX · Kad debit/kredit · e-Wallet · Pindahan bank') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Phase 2: the book's animated version (free sample plays, others locked) --}}
        @if ($videos->isNotEmpty())
            @php
                $video = $videos->first();
                $cta = $showcase->cta(auth()->user());
                $canWatch = auth()->user()?->canWatchVideos() ?? false;
            @endphp
            <section class="mt-12 grid items-center gap-6 rounded-3xl border-2 border-ink bg-lavender p-5 shadow-[0_4px_0_var(--color-ink)] sm:p-8 md:grid-cols-2" aria-labelledby="animation-heading">
                <x-showcase-video :embed="$showcase->embed($video)" :title="$video->title"
                    :thumb="$video->thumbnail_path ? Storage::disk('public')->url($video->thumbnail_path) : null"
                    :duration="$video->durationLabel()"
                    :end-cta="['text' => __('Suka? Tonton semua animasi ASUH dengan Langganan Plus.'), ...$cta]" />
                <div class="min-w-0">
                    <p class="text-sm font-extrabold tracking-wide text-biru uppercase">🎬 {{ __('Ada versi animasi!') }}</p>
                    <h2 id="animation-heading" class="mt-1 font-display text-2xl font-extrabold text-plum">{{ $video->title }}</h2>
                    <p class="mt-2 text-ink/80">
                        @if ($canWatch)
                            {{ __('Termasuk dalam Langganan Plus anda. Anak boleh menontonnya dalam Mod Anak.') }}
                        @elseif ($video->is_free_sample)
                            {{ __('Tonton percuma bersama anak. Animasi lain untuk buku ASUH ada dalam Langganan Plus.') }}
                        @else
                            {{ __('Kisah buku ini dalam bentuk animasi, dalam Langganan Plus bersama semua e-book ASUH.') }}
                        @endif
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ $cta['url'] }}" class="btn-utama">{{ $cta['label'] }}</a>
                        <a href="{{ route('videos.index') }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-ink/20 bg-white px-4 font-bold hover:border-ink">{{ __('Semua video animasi') }}</a>
                    </div>
                </div>
            </section>
        @endif

        @if ($recommended->isNotEmpty())
            <section class="mt-16" aria-labelledby="recommended-heading">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 id="recommended-heading" class="font-display text-2xl font-bold text-plum">{{ __('Anda mungkin suka juga') }}</h2>
                    <a href="{{ route('catalog.index') }}" class="pautan text-sm">{{ __('Lihat semua buku') }}</a>
                </div>

                @unless ($hasPass)
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-ink bg-kuning px-5 py-4">
                        <p class="font-bold">{{ __('Semua buku ini termasuk dalam Langganan Tahunan. Satu bayaran, seisi keluarga baca sepanjang tahun.') }}</p>
                        <a href="{{ route('pass.show') }}" class="btn-gelap">{{ __('Dapatkan Langganan Tahunan') }}</a>
                    </div>
                @endunless

                {{-- A swipeable row on phones, a grid on larger screens. --}}
                <ul class="-mx-4 mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-4 sm:overflow-visible sm:px-0">
                    @foreach ($recommended as $other)
                        <li class="w-36 shrink-0 snap-start sm:w-auto">
                            <a href="{{ route('catalog.show', $other) }}" class="group block">
                                <x-book-cover :book="$other" class="transition group-hover:-translate-y-1" />
                                <p class="mt-2 font-display font-bold leading-tight group-hover:text-plum">{{ $other->localTitle() }}</p>
                                <p class="mt-1 text-xs font-bold">
                                    @if ($other->readable)
                                        <span class="text-biru">✓ {{ __('Boleh dibaca') }}</span>
                                    @elseif ($other->single_price_sen)
                                        <span class="text-magenta">{{ Money::short($other->single_price_sen) }}</span> <span class="text-ink/60">{{ __('atau dengan langganan') }}</span>
                                    @else
                                        <span class="text-ink/60">{{ __('Dengan Langganan Tahunan') }}</span>
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
