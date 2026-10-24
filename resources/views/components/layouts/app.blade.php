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
                <a href="{{ url('/') }}" class="flex min-h-11 items-center gap-2 rounded-lg focus-visible:outline-3 focus-visible:outline-kuning">
                    <img src="{{ Vite::asset('resources/images/brand/logo-asuh.webp') }}" alt="ASUH Digital" width="94" height="40" class="h-10 w-auto">
                </a>

                <nav class="flex items-center gap-3 text-sm font-bold">
                    @if (App\Http\Middleware\EnsureCatalogOpen::isOpenFor(auth()->user()))
                        <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center rounded-full px-3 hover:bg-white/10">Katalog</a>
                    @endif
                    @auth
                        <a href="{{ route('account') }}" class="inline-flex min-h-11 items-center rounded-full px-3 hover:bg-white/10">Akaun saya</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="min-h-11 rounded-full px-3 py-2 hover:bg-white/10">Log keluar</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-full px-3 hover:bg-white/10">Log masuk</a>
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
                <nav aria-label="Maklumat" class="my-2 flex flex-wrap gap-x-4">
                    <a href="{{ route('privacy') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">Notis privasi</a>
                    <a href="{{ route('terms') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">Terma dan syarat</a>
                </nav>
                <p>&copy; {{ now()->year }} ASUH, Galeri Ilmu Media Group.</p>
            </div>
        </footer>
    </body>
</html>
