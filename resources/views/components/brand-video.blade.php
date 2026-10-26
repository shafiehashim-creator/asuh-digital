@props(['place'])

{{--
    The ASUH music video on ASUH's own player (as in Mod Anak): no YouTube
    title links, captions or settings buttons, and ASUH's own pause and end
    screens. Only the video's thumbnail shows until the play button is
    pressed; nothing else loads from YouTube before that.
--}}
@if (App\Support\BrandVideo::shownOn($place))
    @php($id = App\Support\BrandVideo::id())
    <div {{ $attributes }} x-data="{ playing: false }">
        <div x-show="playing" x-cloak x-ref="player">
            <x-video-player :embed="['provider' => 'youtube', 'id' => $id, 'url' => null]" lazy autoplay />
        </div>

        <button type="button" x-show="! playing"
            @click="playing = true; $nextTick(() => window.initVideoPlayer($refs.player.querySelector('[data-video-player]')))"
            class="group relative flex aspect-video w-full flex-col items-center justify-center gap-3 overflow-hidden rounded-3xl border-2 border-ink bg-plum p-4 text-white shadow-[0_4px_0_var(--color-ink)]">
            <img src="https://i.ytimg.com/vi/{{ $id }}/hqdefault.jpg" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
            <span class="absolute inset-0 bg-gradient-to-t from-plum/90 via-plum/30 to-transparent" aria-hidden="true"></span>
            <span class="relative flex size-20 items-center justify-center rounded-full border-4 border-ink bg-kuning text-4xl text-ink shadow-[0_4px_0_var(--color-ink)] transition group-hover:scale-105" aria-hidden="true">▶</span>
            <span class="relative font-display text-xl font-extrabold drop-shadow sm:text-2xl">{{ App\Support\BrandVideo::title() }}</span>
            <span class="sr-only">Mainkan video</span>
        </button>
    </div>
@endif
