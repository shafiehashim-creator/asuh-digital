@props(['catalogOpen' => false, 'videosOpen' => false, 'isTeacher' => false])

{{--
    Phone menu drawer, opened by the Menu tab in the bottom bar (the
    'open-menu' event). Lists everything public on ASUH Digital, the
    family's own pages once logged in, and the school links. Links to
    sections that are still closed (catalogue, videos, Plus) are left out.
--}}
@php
    $user = auth()->user();
    $plusOpen = app(App\Services\PlusOffer::class)->available();

    // Each group: heading (or null), then [label, url, active].
    $groups = array_filter([
        [null, array_values(array_filter([
            [__('Utama'), url('/'), request()->routeIs('home')],
            $catalogOpen ? [__('Buku'), route('catalog.index'), request()->routeIs('catalog.*')] : null,
            $videosOpen ? [__('Video animasi'), route('videos.index'), request()->routeIs('videos.*')] : null,
            [__('Langganan Tahunan'), route('pass.show'), request()->routeIs('pass.*')],
            $plusOpen ? [__('Langganan Plus'), route('plus.show'), request()->routeIs('plus.show', 'purchase.plus*')] : null,
            [__('Tentang ASUH'), route('about'), request()->routeIs('about')],
            [__('Soalan lazim'), url('/').'#soalan', false],
            [__('Pasang app'), route('install'), request()->routeIs('install')],
        ]))],
        $user && ! $isTeacher ? [__('Keluarga saya'), array_values(array_filter([
            [__('Akaun saya'), route('account'), request()->routeIs('account')],
            $catalogOpen ? [__('Anak-anak dan Mod Anak'), route('account.children'), request()->routeIs('account.children', 'children.*')] : null,
            [__('Langganan dan pesanan'), route('account.billing'), request()->routeIs('account.billing')],
        ]))] : null,
        [__('Sekolah dan tadika'), [
            [__('Murid: masuk kelas'), route('pupil.join'), request()->routeIs('pupil.*')],
            [__('Portal guru'), route('teacher.home'), request()->routeIs('teacher.*')],
            [__('Mohon pilot percuma'), route('pilot.create'), request()->routeIs('pilot.*')],
        ]],
    ]);
@endphp

<div x-data="{ open: false }" x-on:open-menu.window="open = true" x-on:keydown.escape.window="open = false" class="md:hidden">
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-ink/50 backdrop-blur-sm" @click="open = false" aria-hidden="true"></div>

    <aside x-show="open" x-cloak
        x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-50 flex w-[85%] max-w-sm flex-col bg-white pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)] text-ink shadow-2xl"
        role="dialog" aria-modal="true" aria-labelledby="mobile-menu-title">
        <div class="flex items-center justify-between border-b-2 border-ink/10 px-5 py-3">
            <p id="mobile-menu-title" class="font-display text-2xl font-extrabold text-plum">{{ __('Menu') }}</p>
            <button type="button" @click="open = false" class="flex size-11 items-center justify-center rounded-2xl border-2 border-ink/15 text-xl hover:border-ink" aria-label="{{ __('Tutup') }}">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
        </div>

        <nav aria-label="{{ __('Menu') }}" class="flex-1 overflow-y-auto px-3 py-3">
            @foreach ($groups as [$heading, $links])
                @if ($heading)
                    <p class="mt-4 px-3 pb-1 text-xs font-extrabold tracking-wide text-ink/50 uppercase">{{ $heading }}</p>
                @endif
                <ul>
                    @foreach ($links as [$label, $href, $active])
                        <li>
                            <a href="{{ $href }}" @click="open = false" @if ($active) aria-current="page" @endif
                                @class(['flex min-h-12 items-center justify-between rounded-2xl px-3 text-lg font-semibold', 'bg-lavender text-plum' => $active, 'hover:bg-latar' => ! $active])>
                                {{ $label }}
                                <span class="text-ink/30" aria-hidden="true">›</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <div class="space-y-3 border-t-2 border-ink/10 px-5 py-4">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-ink/60">{{ __('Bahasa') }}</span>
                <x-locale-switch class="bg-plum" />
            </div>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-full border-2 border-ink/15 font-bold hover:border-ink">{{ __('Log keluar') }}</button>
                </form>
            @else
                <a href="{{ route('register') }}" class="btn-utama w-full">{{ __('Daftar percuma') }}</a>
                <a href="{{ route('login') }}" class="flex min-h-12 w-full items-center justify-center rounded-full border-2 border-ink/15 font-bold hover:border-ink">{{ __('Log masuk') }}</a>
            @endauth
        </div>
    </aside>
</div>
