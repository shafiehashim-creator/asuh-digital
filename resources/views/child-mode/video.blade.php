<x-layouts.child :title="$video->title" :child="$child">
    <div class="mx-auto max-w-5xl px-4 py-6">
        <a href="{{ route('child-mode.home') }}" class="pautan inline-flex min-h-11 items-center text-sm">← Rak</a>
        <h1 class="font-display text-2xl font-extrabold text-magenta sm:text-3xl">{{ $video->title }}</h1>

        <x-video-player class="mt-4" :video="$video" :embed="$embed" :progress-url="$progressUrl" :start-at="$startAt"
            :seconds-left="$secondsLeft" :home-url="route('child-mode.home')" />

        @if ($video->synopsis)
            <p class="mt-4 text-ink/80">{{ $video->synopsis }}</p>
        @endif
    </div>
</x-layouts.child>
