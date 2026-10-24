@props(['title', 'intro' => null])

<x-layouts.app :title="$title">
    <div class="mx-auto max-w-md px-4 py-12">
        <div class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)] sm:p-8">
            <h1 class="font-display text-3xl font-extrabold text-magenta">{{ $title }}</h1>

            @if ($intro)
                <p class="mt-2 text-ink/80">{{ $intro }}</p>
            @endif

            {{-- Fortify also flashes machine codes like "verification-link-sent"; only show sentences. --}}
            @if (session('status') && str_contains(session('status'), ' '))
                <div role="status" class="mt-4 rounded-xl border-2 border-biru/30 bg-biru/5 px-4 py-3 text-sm font-semibold text-biru">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mt-6">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layouts.app>
