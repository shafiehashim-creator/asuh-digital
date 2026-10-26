@props(['video' => null, 'embed', 'progressUrl' => null, 'startAt' => 0, 'secondsLeft' => null, 'homeUrl'])

{{-- The animated video player (Phase 2); see resources/js/video-player.js. --}}
<div id="video-wrap" class="flex flex-col justify-center bg-lavender [&:fullscreen]:p-3">
<div id="video-player"
    class="relative mt-4 aspect-video w-full overflow-hidden rounded-3xl border-2 border-ink bg-ink shadow-[0_4px_0_var(--color-ink)] select-none"
    data-provider="{{ $embed['provider'] }}" data-video-id="{{ $embed['id'] }}" data-embed-url="{{ $embed['url'] }}"
    data-progress-url="{{ $progressUrl }}" data-csrf="{{ csrf_token() }}"
    data-start="{{ $startAt }}" data-seconds-left="{{ $secondsLeft }}">

    <div data-frame class="absolute inset-0 [&_iframe]:h-full [&_iframe]:w-full"></div>

    {{-- Taps land here, never on YouTube's own links. --}}
    <div data-cover class="absolute inset-0 cursor-pointer" aria-hidden="true"></div>

    <div data-loading class="absolute inset-0 flex items-center justify-center font-bold text-white">Memuatkan video…</div>

    <button type="button" data-paused hidden class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-plum/90 text-white">
        <span class="flex size-24 items-center justify-center rounded-full border-4 border-ink bg-kuning text-5xl text-ink shadow-[0_4px_0_var(--color-ink)]" aria-hidden="true">▶</span>
        <span class="font-display text-2xl font-extrabold">Tekan untuk menonton</span>
    </button>

    <div data-ended hidden class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-plum p-6 text-center text-white">
        <p class="text-5xl" aria-hidden="true">🌟</p>
        <p class="font-display text-2xl font-extrabold">Tamat! Jom Jadi Baik!</p>
        <div class="flex flex-wrap justify-center gap-3">
            <button type="button" data-replay class="btn-utama">Tonton semula</button>
            @if ($video?->book && $video->book->isPublished())
                <a href="{{ route('reader.show', $video->book) }}" class="btn-utama">Baca bukunya</a>
            @endif
            <a href="{{ $homeUrl }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-white px-5 font-bold">Kembali ke rak</a>
        </div>
    </div>

    <div data-limit hidden class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-plum p-6 text-center text-white">
        <p class="text-5xl" aria-hidden="true">⏰</p>
        <p class="font-display text-2xl font-extrabold">Masa menonton hari ini sudah habis.</p>
        <p>Jom baca buku pula!</p>
        <a href="{{ $homeUrl }}" class="btn-utama">Ke rak buku</a>
    </div>
</div>

<div data-controls class="mt-3 flex items-center gap-3 rounded-full border-2 border-ink bg-white px-3 py-2">
    <button type="button" data-play class="flex size-12 shrink-0 items-center justify-center rounded-full border-2 border-ink bg-kuning text-xl font-bold shadow-[0_2px_0_var(--color-ink)]" aria-label="Main">▶</button>
    <label class="sr-only" for="video-seek">Kedudukan video</label>
    <input id="video-seek" data-seek type="range" min="0" max="0" value="0" class="h-3 min-w-0 flex-1 accent-biru">
    <span data-time class="shrink-0 font-mono text-sm font-bold">0:00</span>
    <button type="button" data-fullscreen class="flex size-12 shrink-0 items-center justify-center rounded-full border-2 border-ink text-xl" aria-label="Skrin penuh">⛶</button>
</div>
</div>


@vite('resources/js/video-player.js')
