@php
    use App\Enums\OrderStatus;
    use App\Support\Money;

    $statusClasses = [
        OrderStatus::Paid->value => 'bg-biru/10 text-biru',
        OrderStatus::Pending->value => 'bg-oren/15 text-ink',
        OrderStatus::Failed->value => 'bg-ink/10 text-ink',
        OrderStatus::Refunded->value => 'bg-ink/10 text-ink',
    ];
    $pendingPass = $passes->first(fn ($pass) => $pass->starts_at === null);
@endphp

<x-layouts.app title="Akaun saya">
    <div class="mx-auto max-w-4xl space-y-8 px-4 py-10">
        <div>
            <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">Assalamualaikum, {{ $user->name }}</h1>
            <p class="mt-1 text-ink/80">Ini ruang akaun anda: status Langganan Tahunan, pesanan dan maklumat peribadi.</p>
        </div>

        {{-- Pass status --}}
        <section aria-labelledby="pass-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="pass-heading" class="font-display text-xl font-bold text-plum">Langganan Tahunan</h2>

            @if ($activePass)
                <p class="mt-2 text-lg">
                    <span class="font-bold text-biru">Aktif</span> sehingga
                    <strong>{{ $activePass->ends_at->translatedFormat('j F Y') }}</strong>.
                </p>
                @if ($activePass->ends_at->lessThan(now()->addDays(60)))
                    <a href="{{ route('purchase.pass') }}" class="btn-utama mt-4">Perbaharui Langganan Tahunan</a>
                    <p class="mt-2 text-sm text-ink/70">Langganan baharu bermula sebaik langganan semasa tamat.</p>
                @endif
            @elseif ($pendingPass)
                <p class="mt-2 text-lg">
                    Bayaran diterima. Langganan anda akan <strong>bermula pada tarikh platform ASUH Digital dilancarkan</strong>.
                    Kami akan maklumkan melalui e-mel.
                </p>
            @else
                <p class="mt-2 text-ink/80">Anda belum mempunyai Langganan Tahunan yang aktif.</p>
                <a href="{{ route('purchase.pass') }}" class="btn-utama mt-4">Dapatkan Langganan Tahunan</a>
            @endif
        </section>

        {{-- Children --}}
        @if ($shelfOpen)
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

                {{-- Weekly reading report --}}
                <form method="POST" action="{{ route('weekly-report.update') }}" class="mt-6 border-t-2 border-ink/10 pt-5">
                    @csrf
                    @method('PUT')
                    <h3 class="font-display font-bold">Laporan bacaan mingguan</h3>
                    @if (session('status') === 'weekly-report-saved')
                        <p role="status" class="mt-2 text-sm font-semibold text-biru">Tetapan laporan disimpan.</p>
                    @endif
                    <input type="hidden" name="weekly_report" value="0">
                    <label class="mt-2 flex min-h-11 cursor-pointer items-center gap-3">
                        <input type="checkbox" name="weekly_report" value="1" class="size-5 rounded text-biru" @checked($user->weekly_report)>
                        <span>Hantar e-mel setiap Isnin: minit membaca, buku, kuiz dan lencana anak minggu lalu.</span>
                    </label>
                    <button type="submit" class="btn-gelap mt-2">Simpan</button>
                </form>

                {{-- Parent PIN for leaving Mod Anak --}}
                <div class="mt-6 border-t-2 border-ink/10 pt-5">
                    <h3 class="font-display font-bold">PIN ibu bapa</h3>
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
                </div>
            </section>
        @endif

        {{-- Bookshelf --}}
        @if ($shelfOpen)
            <section aria-labelledby="shelf-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="shelf-heading" class="font-display text-xl font-bold text-plum">Rak buku saya</h2>
                    <a href="{{ route('catalog.index') }}" class="pautan text-sm">{{ $canReadAll ? 'Pilih buku lain dalam katalog' : 'Lihat katalog' }}</a>
                </div>

                @if ($canReadAll)
                    <p class="mt-2 text-ink/80">Langganan Tahunan anda membuka <strong>semua buku</strong> dalam katalog.</p>
                @endif

                @if ($recentBooks->isNotEmpty())
                    <h3 class="mt-5 font-display font-bold">Sambung membaca</h3>
                    <div class="mt-3 grid grid-cols-3 gap-4 sm:grid-cols-6">
                        @foreach ($recentBooks as $book)
                            <x-shelf-book :book="$book" />
                        @endforeach
                    </div>
                @endif

                @if ($ownedBooks->isNotEmpty())
                    <h3 class="mt-5 font-display font-bold">Buku yang dibeli</h3>
                    <div class="mt-3 grid grid-cols-3 gap-4 sm:grid-cols-6">
                        @foreach ($ownedBooks as $book)
                            <x-shelf-book :book="$book" />
                        @endforeach
                    </div>
                @endif

                @if ($recentBooks->isEmpty() && $ownedBooks->isEmpty())
                    <p class="mt-2 text-ink/80">
                        {{ $canReadAll ? 'Belum ada buku yang dibuka. Pilih buku pertama untuk anak anda dalam katalog.' : 'Rak anda masih kosong. Buku yang dibeli atau dibaca akan dipaparkan di sini.' }}
                    </p>
                @endif
            </section>
        @endif

        {{-- Orders --}}
        <section aria-labelledby="orders-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="orders-heading" class="font-display text-xl font-bold text-plum">Pesanan saya</h2>

            @if ($orders->isEmpty())
                <p class="mt-2 text-ink/80">Tiada pesanan lagi.</p>
            @else
                <ul class="mt-4 divide-y-2 divide-ink/10">
                    @foreach ($orders as $order)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                            <div>
                                <p class="font-bold">{{ $order->itemName() }}</p>
                                <p class="text-sm text-ink/70">
                                    {{ $order->reference }} · {{ $order->created_at->translatedFormat('j M Y') }} · {{ $order->payment_method->getLabel() }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-bold">{{ Money::format($order->amount_sen) }}</span>
                                <span class="rounded-full px-3 py-1 text-sm font-bold {{ $statusClasses[$order->status->value] }}">
                                    {{ $order->status->getLabel() }}
                                </span>
                                @if ($order->status === OrderStatus::Pending)
                                    <a href="{{ route('preorder.pay', $order) }}" class="pautan text-sm">Teruskan bayaran</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="grid gap-8 md:grid-cols-2">
            {{-- Profile --}}
            <section aria-labelledby="profile-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                <h2 id="profile-heading" class="font-display text-xl font-bold text-plum">Maklumat peribadi</h2>

                @if (session('status') === 'profile-information-updated')
                    <p role="status" class="mt-3 text-sm font-semibold text-biru">Maklumat disimpan.</p>
                @endif

                <form method="POST" action="{{ route('user-profile-information.update') }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')

                    <x-field name="name" label="Nama" :value="$user->name" bag="updateProfileInformation" autocomplete="name" required />
                    <x-field name="email" label="E-mel" type="email" :value="$user->email" bag="updateProfileInformation" autocomplete="email" required
                        hint="Jika ditukar, anda perlu mengesahkan e-mel baharu." />
                    <x-field name="phone" label="No. telefon" type="tel" :value="$user->phone" bag="updateProfileInformation" autocomplete="tel" required
                        hint="Contoh: 012-345 6789" />

                    <button type="submit" class="btn-gelap">Simpan</button>
                </form>
            </section>

            {{-- Password --}}
            <section aria-labelledby="password-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                <h2 id="password-heading" class="font-display text-xl font-bold text-plum">Tukar kata laluan</h2>

                @if (session('status') === 'password-updated')
                    <p role="status" class="mt-3 text-sm font-semibold text-biru">Kata laluan ditukar.</p>
                @endif

                <form method="POST" action="{{ route('user-password.update') }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')

                    <x-field name="current_password" label="Kata laluan semasa" type="password" bag="updatePassword" autocomplete="current-password" required />
                    <x-field name="password" label="Kata laluan baharu" type="password" bag="updatePassword" autocomplete="new-password" required
                        hint="Sekurang-kurangnya 8 aksara, dengan huruf dan nombor." />
                    <x-field name="password_confirmation" label="Taip semula kata laluan baharu" type="password" bag="updatePassword" autocomplete="new-password" required />

                    <button type="submit" class="btn-gelap">Tukar kata laluan</button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.app>
