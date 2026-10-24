@props(['title' => null])

<!DOCTYPE html>
<html lang="ms">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}ASUH Digital</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-latar font-sans text-ink antialiased">
        <header class="bg-plum text-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <a href="{{ url('/') }}" class="flex items-center gap-2 rounded-lg focus-visible:outline-3 focus-visible:outline-kuning">
                    <img src="{{ Vite::asset('resources/images/brand/logo-asuh.png') }}" alt="ASUH Digital" class="h-10 w-auto">
                </a>

                <nav class="flex items-center gap-3 text-sm font-bold">
                    @auth
                        <a href="{{ route('account') }}" class="rounded-full px-3 py-2 hover:bg-white/10">Akaun saya</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="min-h-11 rounded-full px-3 py-2 hover:bg-white/10">Log keluar</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-full px-3 py-2 hover:bg-white/10">Log masuk</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t-2 border-ink/10">
            <div class="mx-auto max-w-6xl px-4 py-8 text-sm text-ink/70">
                <p class="font-display text-base font-bold text-plum">Jom Jadi Baik!</p>
                <p>&copy; {{ now()->year }} ASUH, Galeri Ilmu Media Group.</p>
            </div>
        </footer>
    </body>
</html>
