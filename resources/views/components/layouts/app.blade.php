@props(['title' => null])

@php
    use App\Http\Middleware\EnsureCatalogOpen;

    $catalogOpen = EnsureCatalogOpen::isOpenFor(auth()->user());

    // Bottom tab bar on phones, like an app. Each tab: label, URL, active check, icon path.
    $tabs = array_values(array_filter([
        ['Utama', url('/'), request()->routeIs('home'), 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z'],
        $catalogOpen
            ? ['Katalog', route('catalog.index'), request()->routeIs('catalog.*', 'purchase.book*'), 'M4 4h5a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H4zm16 0h-5a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h6z']
            : null,
        auth()->check()
            ? ['Akaun', route('account'), request()->routeIs('account', 'preorder.pay', 'preorder.receipt'), 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8m-7 9a7 7 0 0 1 14 0']
            : ['Log masuk', route('login'), request()->routeIs('login', 'password.*'), 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8m-7 9a7 7 0 0 1 14 0'],
    ]));
@endphp

<!DOCTYPE html>
<html lang="ms">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ $title ? $title.' · ' : '' }}ASUH Digital</title>
        @include('partials.app-meta')
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-latar pb-[calc(4.5rem+env(safe-area-inset-bottom))] font-sans text-ink antialiased md:pb-0">
        <header class="sticky top-0 z-30 bg-plum pt-[env(safe-area-inset-top)] text-white md:static">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-2 md:py-3">
                <a href="{{ url('/') }}" class="flex min-h-11 items-center gap-2 rounded-lg focus-visible:outline-3 focus-visible:outline-kuning">
                    <img src="{{ Vite::asset('resources/images/brand/logo-asuh.webp') }}" alt="ASUH Digital" width="94" height="40" class="h-8 w-auto md:h-10">
                </a>

                <nav class="hidden items-center gap-3 text-sm font-bold md:flex">
                    @if ($catalogOpen)
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

                <p class="font-display text-sm font-bold text-kuning md:hidden">Jom Jadi Baik!</p>
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="mt-10 border-t-2 border-ink/10 md:mt-16">
            <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-ink/70 md:py-8">
                <p class="hidden font-display text-base font-bold text-plum md:block">Jom Jadi Baik!</p>
                <nav aria-label="Maklumat" class="flex flex-wrap gap-x-4 md:my-2">
                    <a href="{{ route('privacy') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">Notis privasi</a>
                    <a href="{{ route('terms') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">Terma dan syarat</a>
                    @auth
                        <form method="POST" action="{{ route('logout') }}" class="md:hidden">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">Log keluar</button>
                        </form>
                    @endauth
                </nav>
                <p>&copy; {{ now()->year }} ASUH, Galeri Ilmu Media Group.</p>
            </div>
        </footer>

        <nav aria-label="Menu utama" class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-ink bg-white pb-[env(safe-area-inset-bottom)] md:hidden">
            <ul class="mx-auto flex max-w-md">
                @foreach ($tabs as [$label, $href, $active, $icon])
                    <li class="flex-1">
                        <a href="{{ $href }}" @if ($active) aria-current="page" @endif
                            class="group flex min-h-16 flex-col items-center justify-center gap-0.5 text-xs font-bold {{ $active ? 'text-plum' : 'text-ink/60' }}">
                            <span class="flex h-8 w-14 items-center justify-center rounded-full {{ $active ? 'bg-kuning' : 'group-hover:bg-lavender' }}">
                                <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                            </span>
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </body>
</html>
