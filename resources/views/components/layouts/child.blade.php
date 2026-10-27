@props(['title' => null, 'child' => null, 'exitClass' => false])

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
                    @if ($child->isPupil())
                        {{-- Shared class tablets: back to the name list, nothing to protect. --}}
                        <form method="POST" action="{{ route('pupil.leave') }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center rounded-full border-2 border-white/40 px-4 text-sm font-bold hover:bg-white/10">Tukar murid</button>
                        </form>
                    @else
                        {{-- The only way out, behind the parent gate. --}}
                        <a href="{{ route('child-mode.gate') }}" class="inline-flex min-h-11 items-center gap-2 rounded-full border-2 border-ink bg-kuning px-4 text-sm font-bold text-ink shadow-[0_2px_0_var(--color-ink)] hover:bg-kuning/90">
                            <span aria-hidden="true">🔒</span> Keluar<span class="hidden sm:inline">&nbsp;Mod Anak</span>
                        </a>
                    @endif
                @else
                    <p class="flex-1 font-display text-xl font-extrabold">Mod Anak</p>
                    @if ($exitClass)
                        {{-- Class code and name list: a way back to the main site. --}}
                        <form method="POST" action="{{ route('pupil.exit') }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center rounded-full border-2 border-white/40 px-4 text-sm font-bold hover:bg-white/10">← Laman utama</button>
                        </form>
                    @endif
                @endif
            </div>
        </header>

        <main class="pb-[env(safe-area-inset-bottom)]">
            {{ $slot }}
        </main>
    </body>
</html>
