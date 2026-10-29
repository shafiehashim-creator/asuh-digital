@php
    use App\Enums\AgeGroup;
    use App\Enums\CampaignOffer;
    use App\Enums\PassStartRule;
    use App\Http\Controllers\LandingController;
    use App\Support\Money;

    // Early bird first; once its slots are gone the regular pass is sold instead.
    $earlyBird = $offer->earlyBird();
    $onSale = $offer->available();
    // Buttons lead to the sales page first; checkout starts from there.
    $checkoutUrl = route('pass.show');
    $ctaLabel = $offer->ctaLabel();
    $pilotUrl = Route::has('pilot.create') ? route('pilot.create') : null;
    $thumb = fn ($video) => $video->thumbnail_path ? Storage::disk('public')->url($video->thumbnail_path) : null;
    $shelfRow = '-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto px-4 pb-2';

    // One price in the hero: the early bird campaign's (with the normal price struck), else the offer's.
    $soon = $campaign?->isUpcoming() ?? false;
    $campaignPrice = $campaign && $campaign->offer === CampaignOffer::EarlyBird ? $campaignPrices : null;
    [$price, $compareAt] = $campaignPrice ?? [$offer->priceSen(), $offer->compareAtSen()];
    // The campaign's own button when the admin set one.
    $heroCtaUrl = $campaign?->cta_url ?: $checkoutUrl;
    $heroCtaLabel = $campaign?->cta_url ? ($campaign->localized('cta_label') ?: $ctaLabel) : $ctaLabel;
    $showPrice = $price && ($onSale || $campaignPrice);

    // What is inside, with a number only once the number impresses.
    $inside = array_values(array_filter([
        ['📖', $catalogOpen && $stats['books'] >= LandingController::MIN_BOOKS_SHOWN ? $stats['books'] : null, __('E-book Islamik')],
        $videoCount > 0 ? ['🎬', $videoCount >= LandingController::MIN_VIDEOS_SHOWN ? $videoCount : null, __('Video animasi')] : null,
        $gamesOpen ? ['🎮', $gameCount, __('Permainan minda')] : ['🏅', null, __('Kuiz dan lencana')],
    ]));
    $sample = $videoSample ?? $videoRow->first();
@endphp

{{-- The campaign shown in the hero is not repeated in the bar above it; another bar campaign still shows. --}}
<x-layouts.app :campaign-bar="($bar = app(App\Services\Campaigns::class)->bar()) && ! $bar->is($campaign)">
    {{-- Hero: what it is, the real Mod Anak shelf, one price and one button on the first screen. --}}
    <section class="overflow-hidden bg-gradient-to-b from-kuning/35 via-latar to-latar">
        <div class="mx-auto grid max-w-6xl grid-cols-[1fr_7.5rem] gap-x-3 px-4 pt-4 pb-10 sm:grid-cols-[1fr_10rem] md:grid-cols-[1.15fr_1fr] md:gap-x-12 md:py-14">
            <div class="self-center">
                <p class="hidden rounded-full border-2 border-ink bg-white px-3 py-1 text-sm font-extrabold text-ink md:inline-block">🛡️ {{ __('Disemak syariah · Tanpa iklan') }}</p>
                <h1 class="font-display text-[1.6rem] md:mt-3 leading-[1.15] font-extrabold text-magenta sm:text-4xl md:text-5xl">{{ $headline }}</h1>
            </div>

            {{-- A drawing of Mod Anak with the real shelf: books, an animation and the games. --}}
            <figure class="self-center md:row-span-2 md:col-start-2 md:row-start-1">
                <div class="mx-auto w-full max-w-[7.5rem] rotate-2 rounded-[1.4rem] border-[3px] border-ink bg-lavender p-1.5 shadow-[0_5px_0_var(--color-ink)] sm:max-w-[10rem] md:max-w-[19rem] md:rounded-[2.6rem] md:border-[6px] md:p-4" aria-hidden="true">
                    <div class="flex items-center gap-1 rounded-lg bg-plum px-1.5 py-1 text-white md:gap-2 md:rounded-2xl md:px-3 md:py-2">
                        <img src="{{ Vite::asset('resources/images/brand/anis.webp') }}" alt="" class="size-4 rounded-full bg-white object-cover object-top md:size-9">
                        <span class="font-display text-[0.55rem] font-bold md:text-base">{{ __('Hai, Aisyah!') }}</span>
                        <span class="ml-auto rounded-full bg-kuning px-1 text-[0.45rem] font-extrabold text-ink md:px-2 md:text-xs">🔒</span>
                    </div>
                    <p class="mt-1.5 font-display text-[0.5rem] font-bold text-plum md:mt-3 md:text-sm">📖 {{ __('Sambung membaca') }}</p>
                    <div class="mt-1 grid grid-cols-3 gap-1 md:mt-2 md:gap-2">
                        @foreach (range(0, 2) as $i)
                            @if ($heroBooks[$i]?->cover_path ?? null)
                                <img src="{{ Storage::disk('public')->url($heroBooks[$i]->cover_path) }}" alt="" class="aspect-[3/4] w-full rounded border border-ink object-cover md:rounded-lg md:border-2">
                            @else
                                <div @class(['aspect-[3/4] w-full rounded border border-ink md:rounded-lg md:border-2', ['bg-magenta', 'bg-biru', 'bg-oren'][$i]])></div>
                            @endif
                        @endforeach
                    </div>
                    @if ($sample)
                        <p class="mt-1.5 font-display text-[0.5rem] font-bold text-plum md:mt-3 md:text-sm">🎬 {{ __('Video animasi') }}</p>
                        <div class="relative mt-1 aspect-video overflow-hidden rounded border border-ink bg-ink md:mt-2 md:rounded-xl md:border-2">
                            @if ($thumb($sample))
                                <img src="{{ $thumb($sample) }}" alt="" class="h-full w-full object-cover">
                            @endif
                            <span class="absolute inset-0 m-auto grid size-4 place-items-center rounded-full bg-kuning text-[0.45rem] md:size-10 md:text-base">▶</span>
                        </div>
                    @endif
                    @if ($gamesOpen)
                        <p class="mt-1.5 font-display text-[0.5rem] font-bold text-plum md:mt-3 md:text-sm">🎮 {{ __('Permainan') }}</p>
                        <div class="mt-1 grid grid-cols-4 gap-1 md:mt-2 md:gap-2">
                            @foreach (['🃏', '➕', 'ب', '🧩'] as $emoji)
                                <span class="grid aspect-square place-items-center rounded border border-ink bg-white text-[0.6rem] md:rounded-lg md:border-2 md:text-xl">{{ $emoji }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-1.5 font-display text-[0.5rem] font-bold text-plum md:mt-3 md:text-sm">🏅 {{ __('Lencana saya') }}</p>
                        <p class="mt-1 rounded bg-white px-1 py-0.5 text-[0.6rem] md:rounded-lg md:px-2 md:py-1 md:text-xl">🌟 📚 🔥 🏆</p>
                    @endif
                </div>
                <figcaption class="mt-2 hidden text-center text-xs font-semibold text-ink/60 md:block">{{ __('Contoh paparan Mod Anak') }}</figcaption>
            </figure>

            <div class="col-span-2 md:col-span-1 md:col-start-1">
                <p class="mt-3 text-[0.95rem] leading-snug text-ink/80 sm:text-lg">{{ $subheadline }}</p>

                {{-- What is inside --}}
                <ul class="mt-4 grid grid-cols-3 gap-2" aria-label="{{ __('Apa yang ada dalam langganan') }}">
                    @foreach ($inside as [$icon, $count, $label])
                        <li class="flex items-center justify-center gap-1 rounded-2xl border-2 border-ink bg-white px-1 py-1.5 text-center shadow-[0_2px_0_var(--color-ink)] sm:flex-col sm:gap-0">
                            <span class="font-display text-lg leading-none font-extrabold whitespace-nowrap text-magenta sm:text-xl">{{ $icon }}@if ($count) {{ $count }}@endif</span>
                            <span class="text-[0.7rem] leading-tight font-bold sm:mt-1 sm:text-xs">{{ $label }}</span>
                        </li>
                    @endforeach
                </ul>

                {{-- One price, the countdown inside it, one button --}}
                @if ($showPrice)
                    <div @if ($campaign) x-data="countdown({{ $campaign->secondsLeft() }}, {{ $soon ? 'true' : 'false' }})" data-campaign-banner @endif
                        class="mt-4 rounded-3xl border-2 border-ink bg-white p-4 shadow-[0_4px_0_var(--color-ink)] sm:p-5">
                        @if ($campaign)
                            <p @class(['inline-block rounded-full px-3 py-0.5 text-xs font-extrabold text-white', 'bg-biru' => $soon, 'bg-magenta' => ! $soon])>
                                {{ $soon ? '🔜 '.__('Akan datang').' · ' : '🔥 ' }}{{ $campaign->localized('title') }}
                            </p>
                        @endif
                        <div class="flex flex-wrap items-baseline gap-x-3">
                            <p class="font-display text-[2.6rem] leading-tight font-extrabold text-plum sm:text-5xl">{{ Money::short($price) }}</p>
                            @if ($compareAt)
                                <p class="text-xl font-bold text-ink/60 line-through" aria-label="{{ __('Harga biasa :price', ['price' => Money::short($compareAt)]) }}">{{ Money::short($compareAt) }}</p>
                            @endif
                        </div>
                        <p class="text-sm font-bold text-biru">{{ __('Setahun untuk seisi keluarga, hanya :price sebulan', ['price' => Money::format((int) ceil($price / 12))]) }}</p>

                        @if ($campaign)
                            @if ($message = $campaign->localized('message'))
                                <p class="mt-1 text-sm font-semibold">{{ $message }}</p>
                            @endif
                            <p x-show="! ended" class="mt-2 flex flex-wrap items-center gap-2 text-sm font-bold">
                                ⏳ {{ $soon ? __('Bermula dalam') : __('Tamat dalam') }}
                                <span class="rounded-lg bg-ink px-2 py-0.5 font-mono text-kuning tabular-nums"
                                    x-text="(days > 0 ? days + ' {{ __('hari') }} ' : '') + hours + ':' + minutes + ':' + seconds"></span>
                                @if ($campaign->promo_code && ! $soon)
                                    <button type="button" @click="copy('{{ $campaign->promo_code }}')" class="inline-flex min-h-9 items-center gap-1 rounded-full border-2 border-dashed border-ink/40 px-3 hover:bg-kuning/30">
                                        {{ __('Kod') }} <span class="tracking-wide text-magenta">{{ $campaign->promo_code }}</span>
                                        <span class="text-xs text-ink/70" x-text="copied ? '{{ __('Disalin!') }}' : '{{ __('Salin') }}'">{{ __('Salin') }}</span>
                                    </button>
                                @endif
                            </p>
                        @endif
                        @if ($earlyBird)
                            <x-slot-counter :limit="$offer->slotLimit()" :remaining="$offer->slotsRemaining()" class="mt-3" />
                        @endif

                        <a href="{{ $heroCtaUrl }}" class="btn-utama mt-3 w-full text-lg">{{ $heroCtaLabel }} &rarr;</a>
                        @if ($campaignPrice && $soon && $onSale)
                            <p class="mt-2 text-center text-sm">
                                {{ __('Tak mahu tunggu?') }} <a href="{{ $checkoutUrl }}" class="pautan font-bold">{{ __('Langgan sekarang pada :price', ['price' => Money::short($offer->priceSen())]) }}</a>
                            </p>
                        @elseif ($soon)
                            <p class="mt-2 text-center text-sm font-semibold text-ink/70">{{ __('Anda masih boleh membeli sekarang pada harga biasa.') }}</p>
                        @endif
                        @if ($stats['families'] >= App\Services\CatalogStats::MIN_FAMILIES)
                            <p class="mt-2 text-center text-sm font-bold text-plum">👨‍👩‍👧 {{ __(':count keluarga sudah melanggan', ['count' => number_format($stats['families']).'+']) }}</p>
                        @endif

                        <ul class="mt-4 grid grid-cols-3 gap-2 border-t-2 border-ink/10 pt-3 text-center text-xs font-bold">
                            <li><span class="block text-lg" aria-hidden="true">🛡️</span>{{ __('Disemak syariah') }}</li>
                            <li><span class="block text-lg" aria-hidden="true">🚫</span>{{ __('Tanpa iklan') }}</li>
                            <li><span class="block text-lg" aria-hidden="true">📱</span>{{ __('Semua peranti keluarga') }}</li>
                        </ul>
                    </div>
                @else
                    <x-slot-counter :limit="$offer->slotLimit()" :remaining="$offer->slotsRemaining()" class="mt-4 max-w-md" />
                    <span class="btn-utama mt-4" aria-disabled="true">{{ __('Slot early bird telah habis') }}</span>
                @endif

                @if ($videoCount > 0)
                    <a href="#video" class="mt-3 inline-flex min-h-11 items-center font-bold text-plum underline underline-offset-4">▶ {{ __('Tonton video animasi percuma') }}</a>
                @endif
            </div>
        </div>
    </section>

    {{-- Install as an app (only where the device can) --}}
    <x-install-app banner class="mx-auto max-w-3xl px-4 pt-2" />

    {{-- One subscription, three ways: read, watch, play --}}
    <section class="mx-auto max-w-6xl px-4 pt-12" aria-labelledby="ways-heading">
        <p class="text-center text-sm font-extrabold tracking-wide text-oren uppercase">{{ __('Satu langganan') }}</p>
        <h2 id="ways-heading" class="mt-1 text-center font-display text-3xl font-extrabold text-plum sm:text-4xl">
            {{ $gamesOpen ? __('Baca, tonton, kemudian main') : ($videoCount > 0 ? __('Baca, tonton, kemudian uji') : __('Baca, kemudian uji')) }}
        </h2>
        <p class="mx-auto mt-2 max-w-2xl text-center text-ink/80">{{ __('Semuanya dalam Mod Anak, dengan bintang, lencana dan kemajuan yang dikongsi.') }}</p>

        <ul class="{{ $shelfRow }} mt-8 md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0">
            <li class="w-72 shrink-0 snap-start md:w-auto">
                <div class="h-full rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)]">
                    <div class="flex h-32 items-end justify-center gap-2 rounded-2xl bg-lavender p-3" aria-hidden="true">
                        @foreach ($popular ? $popular['books']->slice(3, 3)->whenEmpty(fn ($c) => $popular['books']->take(3)) : collect() as $i => $book)
                            <x-book-cover :book="$book" size="sm" :class="Arr::toCssClasses(['w-16', '-rotate-6' => $loop->first, 'rotate-6' => $loop->last, 'mb-2' => ! $loop->first && ! $loop->last])" />
                        @endforeach
                    </div>
                    <p class="mt-4 inline-block rounded-full bg-magenta px-3 py-0.5 text-xs font-extrabold text-white">📖 {{ __('Baca') }}</p>
                    <h3 class="mt-2 font-display text-xl font-bold text-plum">{{ __('E-book Islamik Galeri Ilmu') }}</h3>
                    <p class="mt-1 text-ink/80">{{ __('Selak macam buku sebenar, ikut umur anak. Kuiz selepas membaca dan lencana untuk yang rajin.') }}</p>
                </div>
            </li>
            @if ($videoCount > 0)
                <li class="w-72 shrink-0 snap-start md:w-auto">
                    <div class="h-full rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)]">
                        <div class="relative h-32 overflow-hidden rounded-2xl bg-ink" aria-hidden="true">
                            @if ($sample && $thumb($sample))
                                <img src="{{ $thumb($sample) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                            @endif
                            <span class="absolute inset-0 m-auto grid size-12 place-items-center rounded-full border-2 border-ink bg-kuning text-xl">▶</span>
                        </div>
                        <p class="mt-4 inline-block rounded-full bg-biru px-3 py-0.5 text-xs font-extrabold text-white">🎬 {{ __('Tonton') }}</p>
                        <h3 class="mt-2 font-display text-xl font-bold text-plum">{{ __('Video animasi kisah buku') }}</h3>
                        <p class="mt-1 text-ink/80">{{ __('Animasi pendek tanpa iklan. Ibu bapa tetapkan had tonton harian.') }}</p>
                    </div>
                </li>
            @endif
            <li class="w-72 shrink-0 snap-start md:w-auto">
                <div class="h-full rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)]">
                    @if ($gamesOpen)
                        <div class="grid h-32 grid-cols-4 gap-2 rounded-2xl bg-lavender p-3" aria-hidden="true">
                            @foreach (['🃏', '😇', '🔢', 'ب', '🔎', '🌙', '➕', '🔬'] as $emoji)
                                <span class="grid place-items-center rounded-xl border-2 border-ink bg-white text-xl">{{ $emoji }}</span>
                            @endforeach
                        </div>
                        <p class="mt-4 inline-block rounded-full bg-oren px-3 py-0.5 text-xs font-extrabold text-ink">🎮 {{ __('Main') }}</p>
                        <h3 class="mt-2 font-display text-xl font-bold text-plum">{{ __(':count permainan minda', ['count' => $gameCount]) }}</h3>
                        <p class="mt-1 text-ink/80">{{ __('Matematik, sains, huruf hijaiyah dan kisah nabi. Tiga tahap, setiap tahap cabaran yang berbeza.') }}</p>
                    @else
                        <div class="grid h-32 place-items-center rounded-2xl bg-lavender text-4xl" aria-hidden="true">🌟 📚 🏆</div>
                        <p class="mt-4 inline-block rounded-full bg-oren px-3 py-0.5 text-xs font-extrabold text-ink">🏅 {{ __('Uji') }}</p>
                        <h3 class="mt-2 font-display text-xl font-bold text-plum">{{ __('Kuiz dan lencana') }}</h3>
                        <p class="mt-1 text-ink/80">{{ __('Kuiz ringkas selepas setiap buku, dan lencana untuk anak yang istiqamah membaca.') }}</p>
                    @endif
                </div>
            </li>
        </ul>
    </section>

    {{-- How old is your child? What each age group gets. --}}
    <section class="mx-auto max-w-4xl px-4 pt-14" x-data="{ age: '6-9' }" aria-labelledby="ages-heading">
        <p class="text-center text-sm font-extrabold tracking-wide text-oren uppercase">{{ __('Ikut umur') }}</p>
        <h2 id="ages-heading" class="mt-1 text-center font-display text-3xl font-extrabold text-plum sm:text-4xl">{{ __('Berapa umur anak anda?') }}</h2>
        <div class="mx-auto mt-6 grid max-w-sm grid-cols-2 gap-3" role="tablist">
            @foreach ([AgeGroup::SixToNine, AgeGroup::TenToThirteen] as $age)
                <button type="button" role="tab" @click="age = '{{ $age->value }}'" :aria-selected="(age === '{{ $age->value }}').toString()"
                    class="min-h-16 rounded-2xl border-2 border-ink font-display text-2xl font-extrabold shadow-[0_3px_0_var(--color-ink)]"
                    :class="age === '{{ $age->value }}' ? 'bg-plum text-white' : 'bg-white'">
                    {{ $age->getLabel() }}
                </button>
            @endforeach
        </div>

        @foreach ([AgeGroup::SixToNine, AgeGroup::TenToThirteen] as $age)
            @php
                $young = $age === AgeGroup::SixToNine;
                $points = array_values(array_filter([
                    ['📖', $young ? __('Buku bergambar, komik dan cerita pendek yang mudah dibaca') : __('Novel kanak-kanak, sirah dan kisah nabi yang lebih panjang')],
                    $videoCount > 0 ? ['🎬', $young ? __('Video animasi pendek, seronok walaupun belum lancar membaca') : __('Video animasi kisah-kisah pilihan')] : null,
                    $gamesOpen ? ['🎮', $young ? __('Permainan: padan kad, huruf hijaiyah, tambah dan tolak') : __('Permainan: sudoku, darab dan bahagi, teka corak, cari perkataan')] : null,
                    ['🏅', $young ? __('Kuiz ringkas selepas membaca, dan lencana') : __('Cabaran Pembaca Terbaik ASUH setiap bulan')],
                ]));
                $ageBooks = $ages[$age->value]['books'];
            @endphp
            <div x-show="age === '{{ $age->value }}'" @if (! $young) x-cloak @endif role="tabpanel" class="mt-6 rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)] sm:p-6">
                <ul class="space-y-3">
                    @foreach ($points as [$icon, $text])
                        <li class="flex gap-3"><span class="text-xl" aria-hidden="true">{{ $icon }}</span><span class="font-semibold">{{ $text }}</span></li>
                    @endforeach
                </ul>
                @if ($ageBooks->isNotEmpty())
                    <p class="mt-5 text-sm font-extrabold text-plum">{{ __('Contoh buku untuk :age', ['age' => $age->getLabel()]) }}</p>
                    <ul class="{{ $shelfRow }} mt-2">
                        @foreach ($ageBooks as $book)
                            <li class="w-24 shrink-0 snap-start sm:w-28">
                                <a href="{{ route('catalog.show', $book) }}"><x-book-cover :book="$book" size="sm" /></a>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('catalog.index', ['umur' => $age->value]) }}" class="pautan mt-2 inline-block text-sm font-bold">{{ __('Semua buku :age', ['age' => $age->getLabel()]) }} &rarr;</a>
                @endif
            </div>
        @endforeach
    </section>

    {{-- The animated videos, included in the yearly subscription --}}
    @if ($videoCount > 0)
        <section id="video" class="mt-14 scroll-mt-4 bg-plum py-14 text-white" aria-labelledby="videos-heading">
            <div class="mx-auto max-w-6xl px-4">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <p class="text-sm font-extrabold tracking-wide text-kuning uppercase">🎬 {{ __('Video animasi · termasuk dalam langganan') }}</p>
                        <h2 id="videos-heading" class="font-display text-3xl font-extrabold">{{ __('Baca kisahnya, tonton animasinya') }}</h2>
                        <p class="mt-2 max-w-2xl text-white/85">{{ __(':count video animasi pendek daripada kisah buku ASUH, disemak syariah dan tanpa iklan. Ibu bapa tetapkan had tonton harian.', ['count' => $videoCount]) }}</p>
                    </div>
                    <a href="{{ route('videos.index') }}" class="font-bold text-kuning underline underline-offset-4">{{ __('Semua video animasi') }} &rarr;</a>
                </div>

                @if ($videoSample)
                    <div class="mx-auto mt-8 max-w-3xl">
                        <x-showcase-video :embed="$showcase->embed($videoSample)" :title="$videoSample->title" :thumb="$thumb($videoSample)" :duration="$videoSample->durationLabel()"
                            :end-cta="['text' => __('Suka? Tonton semua animasi ASUH dengan :plan.', ['plan' => __('Langganan Tahunan')]), 'label' => __('Langgan sekarang'), 'url' => route('pass.show')]" free />
                        <p class="mt-2 text-center text-sm text-white/75">{{ __('Tonton percuma: :title', ['title' => $videoSample->title]) }}</p>
                    </div>
                @endif

                <ul class="{{ $shelfRow }} mt-8">
                    @foreach ($videoRow as $video)
                        <li class="w-60 shrink-0 snap-start">
                            <a href="{{ route('videos.index') }}" class="group block">
                                <div class="relative aspect-video overflow-hidden rounded-2xl border-2 border-ink bg-ink shadow-[0_3px_0_var(--color-ink)]">
                                    @if ($thumb($video))
                                        <img src="{{ $thumb($video) }}" alt="" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-105">
                                    @endif
                                    <span class="absolute bottom-2 left-2 grid size-9 place-items-center rounded-full border-2 border-ink bg-kuning text-ink" aria-hidden="true">▶</span>
                                </div>
                                <p class="mt-2 font-display leading-tight font-bold">{{ $video->title }}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Most read (or picks, before anyone has read) --}}
    @if ($popular && $popular['books']->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pt-14" aria-labelledby="popular-heading">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <p class="text-sm font-extrabold tracking-wide text-oren uppercase">{{ $popular['ranked'] ? __('🔥 Paling popular') : __('⭐ Pilihan kami') }}</p>
                    <h2 id="popular-heading" class="font-display text-3xl font-extrabold text-plum">{{ $popular['ranked'] ? __('Buku kegemaran keluarga') : __('Mula dengan buku-buku ini') }}</h2>
                </div>
                <a href="{{ route('catalog.index') }}" class="pautan text-sm font-bold">{{ __('Semua buku') }} &rarr;</a>
            </div>
            <ul class="{{ $shelfRow }} mt-6">
                @foreach ($popular['books'] as $i => $book)
                    <li class="w-40 shrink-0 snap-start sm:w-48">
                        <x-book-card :book="$book" :rank="$popular['ranked'] && $book->readers_count > 0 ? $i + 1 : null" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Categories --}}
    <section aria-label="{{ __('Kategori buku') }}" @class(['mx-auto max-w-6xl px-4 pt-10' => $catalogOpen, 'mt-14 border-y-2 border-ink bg-plum' => ! $catalogOpen])>
        @if ($catalogOpen)
            <h2 class="font-display text-2xl font-extrabold text-plum">{{ __('Pilih ikut kategori') }}</h2>
            <ul class="chip-row mt-4" x-data="chipRow" :class="more && 'chip-row-more'">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('catalog.index', ['kategori' => $category->slug]) }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-ink bg-white px-4 text-sm font-bold hover:bg-lavender">{{ __($category->name) }}</a>
                    </li>
                @endforeach
            </ul>
        @else
            <ul class="mx-auto flex max-w-6xl flex-wrap justify-center gap-2 px-4 py-5">
                @foreach ($categories as $category)
                    <li class="rounded-full bg-white/10 px-4 py-1.5 text-sm font-bold text-white">{{ __($category->name) }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- The ASUH music video (admin setting) --}}
    @if (App\Support\BrandVideo::shownOn('home'))
        <section class="mx-auto max-w-4xl px-4 pt-16" aria-labelledby="song-heading">
            <p class="text-center text-sm font-extrabold tracking-wide text-oren uppercase">{{ __('Lagu ASUH') }}</p>
            <h2 id="song-heading" class="mt-1 text-center font-display text-3xl font-extrabold text-plum sm:text-4xl">{{ __('Jom nyanyi bersama Anis dan Anas') }}</h2>
            <x-brand-video place="home" class="mt-8" />
        </section>
    @endif

    {{-- Made for parents too --}}
    <section class="mx-auto max-w-6xl px-4 py-16">
        <p class="text-center text-sm font-extrabold tracking-wide text-oren uppercase">{{ __('Kenapa ASUH Digital') }}</p>
        <h2 class="mt-1 text-center font-display text-3xl font-extrabold text-plum sm:text-4xl">{{ __('Direka untuk ibu bapa juga') }}</h2>
        <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-3">
            @foreach ([
                ['🛡️', __('Disemak syariah'), __('Setiap buku, video dan permainan disemak panel syariah sebelum diterbitkan.')],
                ['🧒', __('Mod Anak yang selamat'), __('Tiada harga, butang beli atau tetapan. Keluar hanya melalui pintu ibu bapa.')],
                $videoCount > 0 || $gamesOpen
                    ? ['⏱️', __('Had masa oleh ibu bapa'), __('Tetapkan had tonton dan bermain harian untuk setiap anak.')]
                    : ['📖', __('Selak macam buku sebenar'), __('Mod flip dua muka di tablet dan komputer, satu muka di telefon. Tanpa perlu muat turun aplikasi.')],
                ['📬', __('Laporan mingguan'), __('E-mel setiap Isnin: berapa lama anak membaca, buku yang dibaca dan lencana baharu.')],
                ['👨‍👩‍👧', __('Seisi keluarga'), __('Satu langganan untuk sehingga 4 profil anak, di telefon, tablet atau komputer.')],
                ['🎯', __('Ikut umur anak'), __('Rak buku disusun untuk 6–9 tahun dan 10–13 tahun, jadi anak jumpa bacaan yang sesuai.')],
            ] as [$icon, $title, $body])
                <div class="rounded-3xl border-2 border-ink bg-white p-4 shadow-[0_4px_0_var(--color-ink)] sm:p-6">
                    <p class="text-2xl sm:text-3xl" aria-hidden="true">{{ $icon }}</p>
                    <h3 class="mt-1 font-display text-base leading-tight font-bold text-plum sm:mt-2 sm:text-xl">{{ $title }}</h3>
                    <p class="mt-1 text-sm text-ink/80 sm:mt-2 sm:text-base">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Characters --}}
    <section class="bg-lavender">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <h2 class="text-center font-display text-3xl font-extrabold text-plum sm:text-4xl">{{ __('Kenali watak-watak ASUH') }}</h2>
            <p class="mx-auto mt-3 max-w-2xl text-center text-ink/80">
                {{ __('Anis dan Anas mengajak anak memilih yang baik. Para penggoda pula cuba memesongkan mereka. Setiap kisah membantu anak mengenal beza antara keduanya. Jom Jadi Baik!') }}
            </p>
            <ul class="mt-10 grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach ([
                    ['anis', 'Anis', __('Watak baik'), 'bg-biru text-white'],
                    ['anas', 'Anas', __('Watak baik'), 'bg-biru text-white'],
                    ['syaitan-gemuk', 'Si Gemuk', __('Penggoda'), 'bg-oren text-ink'],
                    ['syaitan-kurus', 'Si Kurus', __('Penggoda'), 'bg-oren text-ink'],
                ] as [$image, $name, $label, $labelClass])
                    <li class="overflow-hidden rounded-3xl border-2 border-ink bg-white shadow-[0_4px_0_var(--color-ink)]">
                        <img src="{{ Vite::asset("resources/images/brand/{$image}.webp") }}" alt="{{ $name }}, {{ Str::lower($label) }}"
                            loading="lazy" class="aspect-[3/4] w-full object-cover object-top">
                        <div class="flex items-center justify-between gap-2 border-t-2 border-ink p-3">
                            <p class="font-display font-bold">{{ $name }}</p>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-extrabold {{ $labelClass }}">{{ $label }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="harga" class="mx-auto max-w-6xl scroll-mt-4 px-4 py-16">
        <div class="mx-auto max-w-xl rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_6px_0_var(--color-ink)] sm:p-8">
            <p class="inline-block rounded-full border-2 border-ink bg-oren px-3 py-0.5 text-sm font-extrabold text-ink">{{ $earlyBird ? __('Harga early bird') : __('Harga Langganan Tahunan') }}</p>
            <h2 class="mt-2 font-display text-3xl font-extrabold text-plum">{{ __('Langganan Tahunan ASUH Digital') }}</h2>

            @if ($onSale)
                <p class="mt-4 font-display text-5xl font-extrabold text-magenta">
                    {{ Money::short($offer->priceSen()) }}
                    @if ($offer->compareAtSen())
                        <span class="text-2xl text-ink/70 line-through">{{ Money::short($offer->compareAtSen()) }}</span>
                    @endif
                </p>
            @endif

            <ul class="mt-6 space-y-3">
                @foreach (array_filter([
                    __('Akses semua e-book ASUH Digital selama 12 bulan'),
                    $videoCount > 0 ? __('Semua :count video animasi ASUH dalam Mod Anak', ['count' => $videoCount]) : null,
                    $gamesOpen ? __(':count permainan minda dengan tiga tahap', ['count' => $gameCount]) : null,
                    __('Sehingga 4 profil anak dalam satu akaun'),
                    __('Mod Anak dengan pintu ibu bapa'),
                    __('Bayaran sekali sahaja, tiada caj automatik'),
                ]) as $benefit)
                    <li class="flex gap-3">
                        <span aria-hidden="true" class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-biru text-sm font-bold text-white">✓</span>
                        <span>{{ $benefit }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($earlyBird || ! $onSale)
                <x-slot-counter :limit="$offer->slotLimit()" :remaining="$offer->slotsRemaining()" class="mt-6" />
            @endif

            @if ($onSale)
                <a href="{{ $checkoutUrl }}" class="btn-utama mt-6 w-full text-lg">{{ $ctaLabel }}</a>
                <x-monthly-option class="mt-3" />
                <a href="{{ route('pass.show') }}" class="pautan mt-4 block text-center text-sm font-bold">{{ __('Kenapa Langganan Tahunan lebih jimat?') }} &rarr;</a>
            @else
                <p class="mt-6 rounded-xl bg-ink/5 p-4 font-semibold">
                    {{ __('Slot early bird telah habis. Terima kasih atas sokongan anda! Langganan Tahunan akan dijual pada harga biasa apabila platform dilancarkan.') }}
                </p>
            @endif
        </div>
    </section>

    {{-- Institutions --}}
    <section class="bg-plum text-white">
        <div class="mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-14 md:flex-row md:items-center md:justify-between">
            <div class="max-w-2xl">
                <h2 class="font-display text-3xl font-extrabold">{{ __('Untuk tadika, SK, SRA, KAFA dan tahfiz') }}</h2>
                <p class="mt-3 text-white/90">
                    {!! __('Cuba ASUH Digital bersama murid anda secara percuma selama :days melalui program pilot institusi.', ['days' => '<strong class="text-kuning">'.e(__(':count hari', ['count' => $pilotDays])).'</strong>']) !!}
                </p>
            </div>
            @if ($pilotUrl)
                <a href="{{ $pilotUrl }}" class="btn-utama shrink-0">{{ __('Mohon pilot percuma') }}</a>
            @endif
        </div>
    </section>

    {{-- FAQ --}}
    <section id="soalan" class="mx-auto max-w-3xl scroll-mt-4 px-4 py-16">
        <h2 class="text-center font-display text-3xl font-extrabold text-plum sm:text-4xl">{{ __('Soalan lazim') }}</h2>

        <div class="mt-8 space-y-3">
            @foreach ($faq as $item)
                <x-faq-item :question="$item['question']">{{ $item['answer'] }}</x-faq-item>
            @endforeach

            <x-faq-item :question="__('Bila Langganan Tahunan saya bermula?')">
                @if ($passStartRule === PassStartRule::OnLaunch)
                    {{ __('Tempoh 12 bulan bermula pada tarikh platform ASUH Digital dilancarkan, jadi anda tidak kehilangan sebarang bulan. Kami akan maklumkan tarikhnya melalui e-mel.') }}
                @else
                    {{ __('Tempoh 12 bulan bermula sebaik sahaja bayaran anda disahkan.') }}
                @endif
            </x-faq-item>

            <x-faq-item :question="__('Boleh saya minta bayaran balik?')">{{ $refundPolicy }}</x-faq-item>
        </div>
    </section>

    {{-- Final call to action --}}
    @if ($onSale)
        <section class="mx-auto max-w-6xl px-4 pb-4">
            <div class="rounded-3xl border-2 border-ink bg-kuning p-8 text-center shadow-[0_6px_0_var(--color-ink)]">
                <h2 class="font-display text-3xl font-extrabold text-ink">{{ __('Jom Jadi Baik bersama ASUH!') }}</h2>
                <p class="mt-2 font-semibold text-ink/80">{{ $earlyBird ? __('Tempah slot early bird sebelum habis.') : ($videoCount > 0 ? __('Satu langganan untuk seisi keluarga: semua e-book dan video animasi selama 12 bulan.') : __('Satu langganan untuk seisi keluarga, semua e-book selama 12 bulan.')) }}</p>
                <a href="{{ $checkoutUrl }}" class="btn-gelap mt-6 text-lg">{{ $ctaLabel }}</a>
            </div>
        </section>
    @endif

    {{-- Phones and computers: the price and the button stay in view after the hero. --}}
    @if ($showPrice)
        <div x-data="{ shown: false }" @scroll.window.throttle.100ms="shown = window.scrollY > 700" x-effect="document.body.classList.toggle('has-sticky-cta', shown)"
            x-show="shown" x-transition.opacity x-cloak
            class="fixed inset-x-0 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-30 border-t-2 border-ink bg-white lg:bottom-0">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-2">
                <p class="min-w-0 leading-tight">
                    <span class="block truncate text-xs font-bold text-ink/70">{{ $campaign ? $campaign->localized('title') : __('Langganan Tahunan') }}</span>
                    <span class="font-display text-2xl font-extrabold text-magenta">{{ Money::short($price) }}</span>
                    @if ($compareAt)
                        <span class="text-sm font-bold text-ink/50 line-through">{{ Money::short($compareAt) }}</span>
                    @endif
                </p>
                <a href="{{ $heroCtaUrl }}" class="btn-utama shrink-0">{{ $heroCtaLabel }}</a>
            </div>
        </div>
    @endif
</x-layouts.app>
