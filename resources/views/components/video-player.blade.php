@props(['video' => null, 'embed', 'progressUrl' => null, 'startAt' => 0, 'secondsLeft' => null, 'homeUrl' => null, 'lazy' => false, 'autoplay' => false, 'endCta' => null])

@php($seekId = 'video-seek-'.Str::random(6))

{{-- The animated video player (Phase 2); see resources/js/video-player.js. --}}
<div data-video-wrap {{ $attributes->class(['flex flex-col justify-center']) }}>
<div data-video-player @if ($lazy) data-lazy @endif data-autoplay="{{ $autoplay ? 1 : 0 }}"
    class="relative aspect-video w-full overflow-hidden rounded-3xl border-2 border-ink bg-ink shadow-[0_4px_0_var(--color-ink)] select-none"
    data-provider="{{ $embed['provider'] }}" data-video-id="{{ $embed['id'] }}" data-embed-url="{{ $embed['url'] }}"
    data-progress-url="{{ $progressUrl }}" data-csrf="{{ csrf_token() }}"
    data-start="{{ $startAt }}" data-seconds-left="{{ $secondsLeft }}"
    data-label-play="{{ __('Main') }}" data-label-pause="{{ __('Jeda') }}"
    data-label-full="{{ __('Skrin penuh') }}" data-label-exit-full="{{ __('Keluar skrin penuh') }}">

    <div data-frame class="absolute inset-0 [&_iframe]:h-full [&_iframe]:w-full"></div>

    {{-- Taps land here, never on YouTube's own links. --}}
    <div data-cover class="absolute inset-0 cursor-pointer" aria-hidden="true"></div>

    <div data-loading class="absolute inset-0 flex items-center justify-center font-bold text-white">{{ __('Memuatkan video…') }}</div>

    <button type="button" data-paused hidden class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-plum/90 text-white">
        <span class="flex size-24 items-center justify-center rounded-full border-4 border-ink bg-kuning text-5xl text-ink shadow-[0_4px_0_var(--color-ink)]" aria-hidden="true">▶</span>
        <span class="font-display text-2xl font-extrabold">{{ __('Tekan untuk menonton') }}</span>
    </button>

    <div data-ended hidden class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-plum p-6 text-center text-white">
        <p class="text-5xl" aria-hidden="true">🌟</p>
        <p class="font-display text-2xl font-extrabold">{{ __('Tamat! Jom Jadi Baik!') }}</p>
        {{-- Free sample on a public page: invite the family to Langganan Plus. --}}
        @if ($endCta)
            <p class="max-w-md font-semibold">{{ $endCta['text'] }}</p>
        @endif
        <div class="flex flex-wrap justify-center gap-3">
            @if ($endCta)
                <a href="{{ $endCta['url'] }}" class="btn-utama">{{ $endCta['label'] }}</a>
            @endif
            <button type="button" data-replay @class(['btn-utama' => ! $endCta, 'inline-flex min-h-11 items-center rounded-full border-2 border-white px-5 font-bold' => $endCta])>{{ __('Tonton semula') }}</button>
            @if ($video?->book && $video->book->isPublished())
                <a href="{{ route('reader.show', $video->book) }}" class="btn-utama">{{ __('Baca bukunya') }}</a>
            @endif
            @if ($homeUrl)
                <a href="{{ $homeUrl }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-white px-5 font-bold">{{ __('Kembali ke rak') }}</a>
            @endif
        </div>
    </div>

    <div data-limit hidden class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-plum p-6 text-center text-white">
        <p class="text-5xl" aria-hidden="true">⏰</p>
        <p class="font-display text-2xl font-extrabold">{{ __('Masa menonton hari ini sudah habis.') }}</p>
        <p>{{ __('Jom baca buku pula!') }}</p>
        @if ($homeUrl)
            <a href="{{ $homeUrl }}" class="btn-utama">{{ __('Ke rak buku') }}</a>
        @endif
    </div>
</div>

<div data-controls class="mt-3 flex items-center gap-3 rounded-full border-2 border-ink bg-white px-3 py-2 text-ink">
    <button type="button" data-play class="flex size-12 shrink-0 items-center justify-center rounded-full border-2 border-ink bg-kuning text-xl font-bold shadow-[0_2px_0_var(--color-ink)]" aria-label="{{ __('Main') }}">▶</button>
    <label class="sr-only" for="{{ $seekId }}">{{ __('Kedudukan video') }}</label>
    <input id="{{ $seekId }}" data-seek type="range" min="0" max="0" value="0" class="h-3 min-w-0 flex-1 accent-biru">
    <span data-time class="shrink-0 font-mono text-sm font-bold">0:00</span>
    {{-- Only on a big screen (app.css): fill it completely, cutting the top and bottom a little. --}}
    <button type="button" data-zoom aria-pressed="false" class="size-12 shrink-0 items-center justify-center rounded-full border-2 border-ink" aria-label="{{ __('Penuhkan skrin') }}">
        {{-- Icons as SVG: symbol characters are missing from many phone fonts. --}}
        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" /></svg>
    </button>
    <button type="button" data-fullscreen class="flex size-12 shrink-0 items-center justify-center rounded-full border-2 border-ink" aria-label="{{ __('Skrin penuh') }}">
        <svg viewBox="0 0 24 24" class="size-6 [.video-big_&]:hidden" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" /></svg>
        <svg viewBox="0 0 24 24" class="hidden size-6 [.video-big_&]:block" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5" /></svg>
    </button>
</div>
</div>


@vite('resources/js/video-player.js')
