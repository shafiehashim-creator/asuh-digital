@props(['place'])

{{--
    The ASUH music video. Only its thumbnail image shows until the play button
    is pressed, then a no-cookie embed without related videos from other
    channels.
--}}
@if (App\Support\BrandVideo::shownOn($place))
    @php($id = App\Support\BrandVideo::id())
    <div {{ $attributes->class(['relative aspect-video w-full overflow-hidden rounded-3xl border-2 border-ink bg-plum shadow-[0_4px_0_var(--color-ink)]']) }}
        x-data="{ playing: false }">
        <template x-if="playing">
            <iframe class="absolute inset-0 h-full w-full"
                src="https://www.youtube-nocookie.com/embed/{{ $id }}?autoplay=1&rel=0&modestbranding=1&playsinline=1"
                title="{{ App\Support\BrandVideo::title() }}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
        </template>
        <button type="button" x-show="! playing" @click="playing = true"
            class="group absolute inset-0 flex flex-col items-center justify-center gap-3 p-4 text-white">
            {{-- The video's own thumbnail (an image only; the player loads on play). --}}
            <img src="https://i.ytimg.com/vi/{{ $id }}/hqdefault.jpg" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
            <span class="absolute inset-0 bg-gradient-to-t from-plum/90 via-plum/30 to-transparent" aria-hidden="true"></span>
            <span class="relative flex size-20 items-center justify-center rounded-full border-4 border-ink bg-kuning text-4xl text-ink shadow-[0_4px_0_var(--color-ink)] transition group-hover:scale-105" aria-hidden="true">▶</span>
            <span class="relative font-display text-xl font-extrabold drop-shadow sm:text-2xl">{{ App\Support\BrandVideo::title() }}</span>
            <span class="sr-only">Mainkan video</span>
        </button>
    </div>
@endif
