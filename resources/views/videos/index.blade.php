@php
    use App\Support\Money;

    $samples = $videos->where('is_free_sample', true);
    // With no trailer, the first free sample takes the hero spot (and leaves the list below).
    $heroSample = $trailerId ? null : $samples->first();
    $listed = $heroSample ? $samples->slice(1) : $samples;
    $locked = $videos->where('is_free_sample', false);
    $thumb = fn ($video) => $video->thumbnail_path ? Storage::disk('public')->url($video->thumbnail_path) : null;
    $endCta = [
        'text' => $lockedCount > 0
            ? __('Suka? Ada :count lagi animasi dalam Langganan Plus.', ['count' => $lockedCount])
            : __('Suka? Tonton semua animasi ASUH dengan Langganan Plus.'),
        ...$cta,
    ];
@endphp

<x-layouts.app :title="__('Video animasi')">
    {{-- Hero with the trailer montage --}}
    <section class="bg-plum text-white">
        <div class="mx-auto grid max-w-6xl items-center gap-8 px-4 py-12 md:grid-cols-2">
            <div class="min-w-0">
                <p class="inline-block rounded-full border-2 border-ink bg-oren px-3 py-1 text-sm font-extrabold text-ink">{{ __('Baharu · Langganan Plus') }}</p>
                <h1 class="mt-4 font-display text-4xl leading-tight font-extrabold text-kuning sm:text-5xl">{{ __('Video animasi ASUH') }}</h1>
                <p class="mt-3 text-lg text-white/90">
                    {{ __('Kisah dalam buku ASUH kini bergerak dan bersuara. Animasi pendek yang disemak syariah, dengan kawalan ibu bapa: had tonton harian dan "baca dulu, tonton kemudian".') }}
                </p>
                <div class="mt-6 flex flex-wrap items-center gap-4">
                    <a href="{{ $cta['url'] }}" class="btn-utama">{{ $cta['label'] }}</a>
                    @if ($plan)
                        <p class="font-semibold text-white/90">{{ __(':price setahun, e-book dan animasi', ['price' => Money::short($plan->price_sen)]) }}</p>
                    @endif
                </div>
            </div>

            @if ($trailerId)
                <x-showcase-video :embed="['provider' => 'youtube', 'id' => $trailerId, 'url' => null]" :title="__('Treler')" :end-cta="$endCta" />
            @elseif ($heroSample)
                <div class="min-w-0">
                    <x-showcase-video :embed="$showcase->embed($heroSample)" :title="$heroSample->title" :thumb="$thumb($heroSample)" :duration="$heroSample->durationLabel()" :end-cta="$endCta" />
                    <p class="mt-2 font-bold">▶ {{ __('Tonton percuma') }}: {{ $heroSample->title }}</p>
                </div>
            @endif
        </div>
    </section>

    <div class="mx-auto max-w-6xl space-y-12 px-4 py-12">
        @if ($listed->isNotEmpty())
            <section aria-labelledby="free-heading">
                <h2 id="free-heading" class="font-display text-2xl font-extrabold text-plum">{{ __('Tonton percuma') }}</h2>
                <p class="mt-1 text-ink/80">{{ __('Tekan main dan tonton bersama anak. Tiada akaun diperlukan.') }}</p>
                <ul class="mt-5 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($listed as $video)
                        <li class="min-w-0">
                            <x-showcase-video :embed="$showcase->embed($video)" :title="$video->title" :thumb="$thumb($video)" :duration="$video->durationLabel()" :end-cta="$endCta" />
                            <h3 class="mt-3 font-display text-lg leading-tight font-bold">{{ $video->title }}</h3>
                            <p class="text-sm text-ink/70">
                                {{ $video->age_group->getLabel() }}
                                @if ($video->book?->isPublished()) · <a href="{{ route('catalog.show', $video->book) }}" class="pautan">{{ __('Baca bukunya') }}</a>@endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($locked->isNotEmpty())
            <section aria-labelledby="plus-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="plus-heading" class="font-display text-2xl font-extrabold text-plum">{{ __('Dalam Langganan Plus') }}</h2>
                        <p class="mt-1 text-ink/80">{{ __(':count animasi lagi menunggu anak anda.', ['count' => $lockedCount]) }}</p>
                    </div>
                    <a href="{{ $cta['url'] }}" class="btn-gelap">{{ $cta['label'] }}</a>
                </div>
                <ul class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($locked as $video)
                        <li class="min-w-0">
                            <x-showcase-video :title="$video->title" :thumb="$thumb($video)" :duration="$video->durationLabel()" />
                            <h3 class="mt-2 truncate font-display font-bold">{{ $video->title }}</h3>
                            <p class="text-xs text-ink/70">{{ $video->age_group->getLabel() }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($videos->isEmpty())
            <p class="rounded-3xl border-2 border-ink/15 bg-white p-6 text-center text-ink/80">{{ __('Video animasi pertama sedang disiapkan. Nantikan!') }}</p>
        @endif

        {{-- Why Plus --}}
        <section class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)] sm:p-8">
            <h2 class="font-display text-2xl font-extrabold text-plum">{{ __('Langganan Plus: baca dan tonton') }}</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ([
                    __('Semua e-book ASUH, ditambah semua video animasi'),
                    __('Disemak syariah sebelum diterbitkan'),
                    __('Had tonton harian yang ibu bapa tetapkan'),
                    __('"Baca dulu, tonton kemudian": video dibuka selepas anak membaca bukunya'),
                    __('Tiada iklan dan tiada pautan keluar ke YouTube'),
                    __('Sehingga 4 profil anak, sekali bayar setahun'),
                ] as $point)
                    <li class="flex gap-2"><span class="font-bold text-biru" aria-hidden="true">✓</span> {{ $point }}</li>
                @endforeach
            </ul>
            <a href="{{ $cta['url'] }}" class="btn-utama mt-6">{{ $cta['label'] }}</a>
        </section>
    </div>
</x-layouts.app>
