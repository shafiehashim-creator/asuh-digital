@props(['title' => null, 'child' => null])

{{-- Mod Anak: no prices, buy buttons, account links or log out (CLAUDE.md). --}}
<!DOCTYPE html>
<html lang="ms">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $title ? $title.' · ' : '' }}Mod Anak · ASUH Digital</title>
        @include('partials.app-meta')
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-lavender font-sans text-ink antialiased">
        <header class="bg-plum pt-[env(safe-area-inset-top)] text-white">
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3">
                @if ($child)
                    <x-child-avatar :avatar="$child->avatar" />
                    <p class="min-w-0 flex-1 truncate font-display text-xl font-extrabold">Hai, {{ $child->nickname }}!</p>
                @else
                    <p class="flex-1 font-display text-xl font-extrabold">Mod Anak</p>
                @endif
                <a href="{{ route('child-mode.gate') }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-white/40 px-4 text-sm font-bold hover:bg-white/10">
                    Ibu bapa
                </a>
            </div>
        </header>

        <main class="pb-[env(safe-area-inset-bottom)]">
            {{ $slot }}
        </main>
    </body>
</html>
