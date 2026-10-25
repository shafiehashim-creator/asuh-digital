<x-layouts.account title="Anak-anak" heading="Anak-anak" intro="Profil anak, Mod Anak dan PIN untuk keluar dari Mod Anak." :$activePass :$pendingPass :$shelfOpen :$plusUpgradePrice>
    <section aria-labelledby="children-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
        <h2 id="children-heading" class="font-display text-xl font-bold text-plum">Profil anak</h2>

        @if (session('status') === 'child-saved')
            <p role="status" class="mt-3 text-sm font-semibold text-biru">Profil anak disimpan.</p>
        @elseif (session('status') === 'child-deleted')
            <p role="status" class="mt-3 text-sm font-semibold text-biru">Profil anak dan semua data bacaannya telah dipadam.</p>
        @elseif (session('status') === 'children-full')
            <p role="status" class="mt-3 text-sm font-semibold text-magenta">Setiap akaun boleh mempunyai sehingga {{ App\Models\Child::MAX_PER_ACCOUNT }} profil anak.</p>
        @endif

        @if ($children->isEmpty())
            <p class="mt-2 text-ink/80">Tambah profil untuk setiap anak supaya mereka mendapat rak buku ikut umur sendiri.</p>
        @else
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($children as $child)
                    <li class="rounded-2xl border-2 border-ink/15 p-3">
                        <div class="flex items-center gap-3">
                            <x-child-avatar :avatar="$child->avatar" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-display text-lg font-bold">{{ $child->nickname }}</p>
                                <p class="text-sm text-ink/70">{{ $child->age_group->getLabel() }}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <a href="{{ route('children.edit', $child) }}" class="pautan inline-flex min-h-11 items-center text-sm">Ubah profil</a>
                            <form method="POST" action="{{ route('children.child-mode', $child) }}">
                                @csrf
                                <button type="submit" class="btn-utama px-4 text-sm">Mula Mod Anak</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($children->count() < App\Models\Child::MAX_PER_ACCOUNT)
            <a href="{{ route('children.create') }}" class="btn-gelap mt-4">Tambah anak</a>
        @endif
    </section>

    {{-- Parent PIN for leaving Mod Anak --}}
    <section aria-labelledby="pin-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
        <h2 id="pin-heading" class="font-display text-xl font-bold text-plum">PIN ibu bapa</h2>
        @if (session('status') === 'pin-saved')
            <p role="status" class="mt-2 text-sm font-semibold text-biru">PIN disimpan.</p>
        @elseif (session('status') === 'pin-removed')
            <p role="status" class="mt-2 text-sm font-semibold text-biru">PIN dibuang. Soalan matematik akan digunakan.</p>
        @endif
        <p class="mt-1 text-sm text-ink/80">
            {{ $user->hasParentPin()
                ? 'Mod Anak meminta PIN 4 digit anda sebelum keluar.'
                : 'Tanpa PIN, Mod Anak bertanya soalan matematik sebelum keluar. Tetapkan PIN jika anak anda sudah pandai mengira.' }}
        </p>

        <details class="mt-3" @if ($errors->parentPin->any()) open @endif>
            <summary class="inline-flex min-h-11 cursor-pointer items-center font-bold text-plum underline">{{ $user->hasParentPin() ? 'Tukar atau buang PIN' : 'Tetapkan PIN' }}</summary>
            <form method="POST" action="{{ route('parent-pin.update') }}" class="mt-3 grid gap-3 sm:grid-cols-3">
                @csrf
                @method('PUT')
                <x-field name="pin" label="{{ $user->hasParentPin() ? 'PIN baharu' : 'PIN (4 digit)' }}" type="password" bag="parentPin"
                    inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off" required />
                <x-field name="pin_confirmation" label="Taip semula PIN" type="password" bag="parentPin"
                    inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off" required />
                <x-field name="current_password" label="Kata laluan akaun" type="password" bag="parentPin" autocomplete="current-password" required />
                <div class="flex flex-wrap items-center gap-3 sm:col-span-3">
                    <button type="submit" class="btn-gelap">{{ $user->hasParentPin() ? 'Tukar PIN' : 'Tetapkan PIN' }}</button>
                </div>
            </form>

            @if ($user->hasParentPin())
                <form method="POST" action="{{ route('parent-pin.destroy') }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    @method('DELETE')
                    <div class="min-w-48 flex-1 sm:max-w-xs">
                        <label for="remove_pin_password" class="label">Kata laluan akaun (untuk buang PIN)</label>
                        <input id="remove_pin_password" name="current_password" type="password" class="input" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="pautan inline-flex min-h-11 items-center text-sm">Buang PIN</button>
                </form>
            @endif
        </details>
    </section>
</x-layouts.account>
