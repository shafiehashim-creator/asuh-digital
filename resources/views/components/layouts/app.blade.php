@props(['title' => null])

@php
    use App\Http\Middleware\EnsureCatalogOpen;

    $catalogOpen = EnsureCatalogOpen::isOpenFor(auth()->user());
    $videosOpen = app(App\Services\VideoShowcase::class)->isOpenFor(auth()->user());
    // Teachers (Phase D) have the teacher portal instead of a parent's account page.
    $isTeacher = auth()->user()?->isInstitutionStaff() ?? false;

    // Bottom tab bar on phones, like an app. Each tab: label, URL, active check, icon path.
    $tabs = array_values(array_filter([
        [__('Utama'), url('/'), request()->routeIs('home'), 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z'],
        $catalogOpen
            ? [__('Buku'), route('catalog.index'), request()->routeIs('catalog.*', 'purchase.book*'), 'M4 4h5a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H4zm16 0h-5a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h6z']
            : null,
        // Phase 2: only once the animated videos are open.
        $videosOpen
            ? [__('Video'), route('videos.index'), request()->routeIs('videos.*', 'plus.show', 'purchase.plus*'), 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1m6 4v6l5-3z']
            : null,
        auth()->check()
            ? [$isTeacher ? __('Guru') : __('Keluarga'), $isTeacher ? route('teacher.home') : route('account'), request()->routeIs('account', 'account.*', 'children.*', 'preorder.pay', 'preorder.receipt', 'teacher.*'), 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8m-7 9a7 7 0 0 1 14 0']
            : [__('Log masuk'), route('login'), request()->routeIs('login', 'password.*'), 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8m-7 9a7 7 0 0 1 14 0'],
    ]));

    // Computers: the same places as the phone Menu drawer, with Langganan and Sekolah as dropdowns.
    // Each entry: label, URL (or a list of [label, URL, active] for a dropdown), active.
    $plusOpen = app(App\Services\PlusOffer::class)->available();
    $subscriptions = array_values(array_filter([
        [__('Langganan Tahunan'), route('pass.show'), request()->routeIs('pass.show', 'purchase.pass*', 'preorder.*')],
        $plusOpen ? [__('Langganan Plus'), route('plus.show'), request()->routeIs('plus.show', 'purchase.plus*')] : null,
    ]));
    $schools = [
        [__('Murid: masuk kelas'), route('pupil.join'), request()->routeIs('pupil.*')],
        [__('Portal guru'), route('teacher.home'), request()->routeIs('teacher.*')],
        [__('Mohon pilot percuma'), route('pilot.create'), request()->routeIs('pilot.*')],
    ];
    $desktopMenu = array_values(array_filter([
        $catalogOpen ? [__('Buku'), route('catalog.index'), request()->routeIs('catalog.*', 'purchase.book*')] : null,
        $videosOpen ? [__('Video'), route('videos.index'), request()->routeIs('videos.*')] : null,
        count($subscriptions) > 1
            ? [__('Langganan'), $subscriptions, collect($subscriptions)->contains(fn ($item) => $item[2])]
            : [__('Langganan'), $subscriptions[0][1], $subscriptions[0][2]],
        [__('Sekolah'), $schools, collect($schools)->contains(fn ($item) => $item[2])],
        [__('Tentang ASUH'), route('about'), request()->routeIs('about')],
    ]));
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ $title ? __($title).' · ' : '' }}ASUH Digital</title>
        @include('partials.app-meta')
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-latar pb-[calc(4.5rem+env(safe-area-inset-bottom))] font-sans text-ink antialiased lg:pb-0">
        <header class="sticky top-0 z-30 bg-plum pt-[env(safe-area-inset-top)] text-white lg:static">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-2 lg:py-3">
                <a href="{{ url('/') }}" class="flex min-h-11 items-center gap-2 rounded-lg focus-visible:outline-3 focus-visible:outline-kuning">
                    <img src="{{ Vite::asset('resources/images/brand/logo-asuh.webp') }}" alt="ASUH Digital" width="94" height="40" class="h-8 w-auto lg:h-10">
                </a>

                {{-- Computers (1024px and wider): the full menu. Phones and tablets use the bottom tab bar and the Menu drawer. --}}
                <nav aria-label="{{ __('Menu utama') }}" class="hidden items-center gap-1 text-sm font-bold lg:flex">
                    @foreach ($desktopMenu as [$label, $target, $active])
                        @if (is_array($target))
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                                <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="true"
                                    @class(['inline-flex min-h-11 items-center gap-1 rounded-full px-3 hover:bg-white/10', 'bg-white/15' => $active])>
                                    {{ $label }}
                                    <svg viewBox="0 0 20 20" class="size-4 transition" :class="open && 'rotate-180'" fill="currentColor" aria-hidden="true"><path d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4" /></svg>
                                </button>
                                <ul x-show="open" x-cloak x-transition.opacity class="absolute left-0 top-full z-40 mt-2 min-w-56 rounded-2xl border-2 border-ink bg-white p-2 text-ink shadow-[0_4px_0_var(--color-ink)]">
                                    @foreach ($target as [$itemLabel, $href, $itemActive])
                                        <li>
                                            <a href="{{ $href }}" @if ($itemActive) aria-current="page" @endif
                                                @class(['flex min-h-11 items-center rounded-xl px-3', 'bg-lavender text-plum' => $itemActive, 'hover:bg-latar' => ! $itemActive])>{{ $itemLabel }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <a href="{{ $target }}" @if ($active) aria-current="page" @endif
                                @class(['inline-flex min-h-11 items-center rounded-full px-3 hover:bg-white/10', 'bg-white/15' => $active])>{{ $label }}</a>
                        @endif
                    @endforeach
                    <span class="mx-1 h-6 w-px bg-white/25" aria-hidden="true"></span>
                    @auth
                        <a href="{{ $isTeacher ? route('teacher.home') : route('account') }}" class="inline-flex min-h-11 items-center rounded-full px-3 hover:bg-white/10">{{ $isTeacher ? __('Portal guru') : __('Akaun saya') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="min-h-11 rounded-full px-3 py-2 hover:bg-white/10">{{ __('Log keluar') }}</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-full px-3 hover:bg-white/10">{{ __('Log masuk') }}</a>
                        <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-white/40 px-4 hover:bg-white/10">{{ __('Daftar') }}</a>
                    @endauth
                    <x-locale-switch class="ml-2" />
                </nav>

                <div class="flex items-center gap-3 lg:hidden">
                    <p class="font-display text-sm font-bold text-kuning">Jom Jadi Baik!</p>
                    <x-locale-switch />
                </div>
            </div>
            <x-campaign-bar />
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="mt-10 border-t-2 border-ink/10 md:mt-16">
            <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-ink/70 md:py-8">
                <p class="hidden font-display text-base font-bold text-plum lg:block">Jom Jadi Baik!</p>
                <nav aria-label="{{ __('Maklumat') }}" class="flex flex-wrap gap-x-4 md:my-2">
                    <a href="{{ route('about') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">{{ __('Tentang kami') }}</a>
                    <a href="{{ route('install') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">{{ __('Pasang app') }}</a>
                    @if ($videosOpen)
                        <a href="{{ route('videos.index') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">{{ __('Video animasi') }}</a>
                    @endif
                    <a href="{{ route('privacy') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">{{ __('Notis privasi') }}</a>
                    <a href="{{ route('terms') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">{{ __('Terma dan syarat') }}</a>
                    <a href="{{ route('refund') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">{{ __('Polisi bayaran balik') }}</a>
                    <a href="{{ route('delivery') }}" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">{{ __('Polisi penghantaran') }}</a>
                    @auth
                        <form method="POST" action="{{ route('logout') }}" class="lg:hidden">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center font-semibold hover:text-plum">{{ __('Log keluar') }}</button>
                        </form>
                    @endauth
                </nav>
                <p>&copy; {{ now()->year }} ASUH, Galeri Ilmu Media Group.</p>
            </div>
        </footer>

        @if ($whatsapp = App\Models\Setting::get('whatsapp_number'))
            <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode(__('Assalamualaikum, saya ada pertanyaan tentang ASUH Digital.')) }}"
                target="_blank" rel="noopener" aria-label="{{ __('Tanya kami di WhatsApp') }}"
                class="fixed right-4 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-40 grid size-14 place-items-center rounded-full border-2 border-ink bg-[#25D366] text-white shadow-[0_3px_0_var(--color-ink)] lg:bottom-6">
                <svg viewBox="0 0 24 24" class="size-7" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2m0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2m4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4a.5.5 0 0 0 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a.9.9 0 0 0-.7.3 2.8 2.8 0 0 0-.9 2.1 4.9 4.9 0 0 0 1 2.6 11.2 11.2 0 0 0 4.3 3.8c1.6.7 2.2.7 3 .6a2.6 2.6 0 0 0 1.7-1.2 2.1 2.1 0 0 0 .1-1.2c0-.1-.2-.2-.4-.3"/></svg>
            </a>
        @endif

        <nav aria-label="{{ __('Menu utama') }}" class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-ink bg-white pb-[env(safe-area-inset-bottom)] lg:hidden">
            <ul class="mx-auto flex max-w-md md:max-w-xl">
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
                <li class="flex-1">
                    <button type="button" x-data @click="$dispatch('open-menu')"
                        class="group flex min-h-16 w-full flex-col items-center justify-center gap-0.5 text-xs font-bold text-ink/60">
                        <span class="flex h-8 w-14 items-center justify-center rounded-full group-hover:bg-lavender">
                            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </span>
                        {{ __('Menu') }}
                    </button>
                </li>
            </ul>
        </nav>

        <x-mobile-menu :catalog-open="$catalogOpen" :videos-open="$videosOpen" :is-teacher="$isTeacher" />
    </body>
</html>
