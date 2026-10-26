<x-layouts.child :title="App\Support\BrandVideo::title()" :child="$child">
    <div class="mx-auto max-w-5xl px-4 py-6">
        <a href="{{ route('child-mode.home') }}" class="pautan inline-flex min-h-11 items-center text-sm">← Rak</a>
        <h1 class="font-display text-2xl font-extrabold text-magenta sm:text-3xl">{{ App\Support\BrandVideo::title() }}</h1>

        <x-video-player :embed="$embed" :home-url="route('child-mode.home')" />
    </div>
</x-layouts.child>
