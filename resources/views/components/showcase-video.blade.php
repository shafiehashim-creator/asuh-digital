@props(['embed' => null, 'title', 'thumb' => null, 'duration' => null, 'endCta' => null])

{{--
    One animated video on a public page (Phase 2). A free sample (or the
    trailer) shows its thumbnail until play is pressed, then plays in full on
    ASUH's own player. Any other video is locked: thumbnail only, and its
    provider id is never sent to the browser.
--}}
@php
    $cover = $thumb ?? ($embed && $embed['provider'] === 'youtube' ? "https://i.ytimg.com/vi/{$embed['id']}/hqdefault.jpg" : null);
    $frame = 'group relative flex aspect-video w-full flex-col items-center justify-center gap-2 overflow-hidden rounded-3xl border-2 border-ink bg-plum p-4 text-white shadow-[0_4px_0_var(--color-ink)]';
@endphp

@if ($embed)
    <div {{ $attributes->class(['min-w-0']) }} x-data="{ playing: false }">
        <div x-show="playing" x-cloak x-ref="player">
            <x-video-player :embed="$embed" :end-cta="$endCta" lazy autoplay />
        </div>

        <button type="button" x-show="! playing" class="{{ $frame }}"
            @click="playing = true; $nextTick(() => window.initVideoPlayer($refs.player.querySelector('[data-video-player]')))">
            @if ($cover)
                <img src="{{ $cover }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
            @endif
            <span class="absolute inset-0 bg-gradient-to-t from-plum/90 via-plum/30 to-transparent" aria-hidden="true"></span>
            <span class="relative flex size-16 items-center justify-center rounded-full border-4 border-ink bg-kuning text-3xl text-ink shadow-[0_4px_0_var(--color-ink)] transition group-hover:scale-105" aria-hidden="true">▶</span>
            <span class="sr-only">{{ __('Mainkan video') }}: {{ $title }}</span>
            @if ($duration)
                <span class="absolute right-3 bottom-3 rounded-full bg-ink/80 px-2 py-0.5 text-xs font-bold">{{ $duration }}</span>
            @endif
        </button>
    </div>
@else
    <div {{ $attributes->class(['min-w-0']) }}>
        {{-- The thumbnail stays clear; the lock overlay shows on hover (a small badge always). --}}
        <div class="{{ $frame }}" tabindex="0">
            @if ($cover)
                <img src="{{ $cover }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
            @endif
            <span class="absolute top-3 left-3 flex items-center gap-1 rounded-full border-2 border-ink bg-kuning px-2 py-0.5 text-xs font-extrabold text-ink transition group-hover:opacity-0 group-focus:opacity-0">🔒 {{ __('Plus') }}</span>
            <span class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-plum/75 opacity-0 transition group-hover:opacity-100 group-focus:opacity-100">
                <span class="flex size-14 items-center justify-center rounded-full border-2 border-white/60 bg-ink/50 text-2xl" aria-hidden="true">🔒</span>
                <span class="text-sm font-bold">{{ __('Dalam Langganan Plus') }}</span>
            </span>
            @if ($duration)
                <span class="absolute right-3 bottom-3 rounded-full bg-ink/80 px-2 py-0.5 text-xs font-bold">{{ $duration }}</span>
            @endif
        </div>
    </div>
@endif
