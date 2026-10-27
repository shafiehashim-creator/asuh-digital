{{-- One animated video on the Mod Anak shelf: ▶ to play, 🔒 with the reason when locked. --}}
<a href="{{ route('child-mode.video', $video) }}" class="group block w-64 shrink-0 snap-start">
    <div class="relative aspect-video overflow-hidden rounded-2xl border-2 border-ink bg-plum shadow-[0_3px_0_var(--color-ink)]">
        @if ($video->thumbnail_path)
            <img src="{{ Storage::disk('public')->url($video->thumbnail_path) }}" alt="" loading="lazy" @class(['h-full w-full object-cover', 'opacity-40 grayscale' => $locked])>
        @else
            <div class="flex h-full items-center justify-center p-3 text-center font-display text-lg font-extrabold text-white">{{ $video->title }}</div>
        @endif
        <span class="absolute bottom-2 left-2 flex size-10 items-center justify-center rounded-full border-2 border-ink bg-kuning text-lg text-ink" aria-hidden="true">{{ $locked ? '🔒' : '▶' }}</span>
        @if ($video->durationLabel())
            <span class="absolute right-2 bottom-2 rounded-full bg-ink/80 px-2 text-xs font-bold text-white">{{ $video->durationLabel() }}</span>
        @endif
    </div>
    <p class="mt-2 font-display leading-tight font-bold group-hover:text-plum">{{ $video->title }}</p>
    @if ($locked)
        <p class="text-sm font-semibold text-magenta">{{ $locked }}</p>
    @endif
</a>
