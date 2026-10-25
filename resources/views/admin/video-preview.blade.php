<x-layouts.app :title="'Pratonton: '.$video->title">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <a href="{{ $backUrl }}" class="pautan text-sm">← Kembali ke admin</a>
        <p class="mt-3 inline-block rounded-full bg-oren px-3 py-0.5 text-sm font-extrabold">Pratonton staf · {{ $video->status->getLabel() }}</p>
        <h1 class="mt-2 font-display text-3xl font-extrabold text-magenta">{{ $video->title }}</h1>

        <x-video-player :video="$video" :embed="$embed" :home-url="$backUrl" />
    </div>
</x-layouts.app>
