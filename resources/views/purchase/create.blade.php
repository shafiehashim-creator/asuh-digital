@php
    use App\Enums\PaymentMethod;
    use App\Models\Setting;

    $user = auth()->user();
    $cardClass = 'rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)] sm:p-8';
    $bankHours = (int) Setting::get('bank_transfer_reservation_hours');
    $schoolCode ??= false;
@endphp

<x-layouts.app :title="$title">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">{{ $title }}</h1>

        <div class="mt-4 rounded-2xl border-2 border-ink bg-kuning px-5 py-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="font-display text-lg font-bold">{{ $item }}</p>
                <p class="font-display text-3xl font-extrabold">{{ $price }}</p>
            </div>
            <ul class="mt-2 space-y-1 text-sm font-semibold">
                @foreach ($details as $detail)
                    <li>✓ {{ $detail }}</li>
                @endforeach
            </ul>
        </div>

        @if ($note)
            <p role="note" class="mt-4 rounded-xl border-2 border-biru/30 bg-biru/5 px-4 py-3 text-sm font-semibold text-biru">{{ $note }}</p>
        @endif

        @error('purchase')
            <div role="alert" class="mt-6 rounded-xl border-2 border-magenta bg-magenta/5 px-4 py-3 font-semibold text-magenta">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ $action }}" class="mt-8 space-y-8" novalidate>
            @csrf

            <section class="{{ $cardClass }} space-y-5" aria-labelledby="akaun-heading">
                <h2 id="akaun-heading" class="font-display text-xl font-bold text-plum">1. Akaun anda</h2>

                @if ($user)
                    <p class="text-ink/80">Anda log masuk sebagai <strong>{{ $user->name }}</strong> ({{ $user->email }}).</p>
                @else
                    <p class="text-sm text-ink/80">
                        Sudah ada akaun? <a href="{{ route('login') }}" class="pautan">Log masuk</a>
                    </p>
                    <x-field name="name" label="Nama penuh" autocomplete="name" required />
                    <x-field name="email" label="E-mel" type="email" autocomplete="email" required hint="Resit dan pengesahan akaun dihantar ke sini." />
                    <x-field name="phone" label="No. telefon" type="tel" autocomplete="tel" required hint="Contoh: 012-345 6789" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="password" label="Kata laluan" type="password" autocomplete="new-password" required hint="Sekurang-kurangnya 8 aksara, dengan huruf dan nombor." />
                        <x-field name="password_confirmation" label="Taip semula kata laluan" type="password" autocomplete="new-password" required />
                    </div>
                @endif
            </section>

            @if ($schoolCode)
                {{-- Parents of pupils at a licensed school get its discount. --}}
                <details class="{{ $cardClass }}" @if (old('school_code') || $errors->has('school_code')) open @endif>
                    <summary class="cursor-pointer font-display text-lg font-bold text-plum">Ada kod sekolah? Dapatkan diskaun</summary>
                    <div class="mt-4">
                        <x-field name="school_code" label="Kod sekolah" autocomplete="off" class="uppercase"
                            hint="Diberi oleh tadika atau sekolah anak anda yang menggunakan ASUH Digital. Diskaun dikira pada halaman bayaran." />
                    </div>
                </details>
            @endif

            <section class="{{ $cardClass }} space-y-4" aria-labelledby="bayaran-heading">
                <h2 id="bayaran-heading" class="font-display text-xl font-bold text-plum">2. Kaedah bayaran</h2>

                @foreach ([
                    [PaymentMethod::Chip, 'Bayar dalam talian sekarang dan akses dibuka serta-merta.'],
                    [PaymentMethod::BankTransfer, 'Pindahkan ke akaun bank kami dan muat naik resit. Akses dibuka selepas kami sahkan bayaran.'],
                ] as [$method, $text])
                    <label class="flex cursor-pointer gap-3 rounded-xl border-2 border-ink/20 p-4 has-checked:border-biru has-checked:bg-biru/5">
                        <input type="radio" name="payment_method" value="{{ $method->value }}" @checked(old('payment_method', PaymentMethod::Chip->value) === $method->value) class="mt-1 size-5 shrink-0 text-biru" required>
                        <span>
                            <span class="block font-bold">{{ $method->getLabel() }}</span>
                            <span class="block text-sm text-ink/80">{{ $text }}</span>
                        </span>
                    </label>
                @endforeach
                @error('payment_method') <p class="text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
            </section>

            <div class="space-y-5">
                @guest
                    <label class="flex cursor-pointer gap-3">
                        <input type="checkbox" name="pdpa_consent" value="1" @checked(old('pdpa_consent')) class="mt-0.5 size-5 shrink-0 rounded border-2 border-ink/30 text-biru" required>
                        <span class="text-sm">
                            Saya bersetuju dengan <a href="{{ route('terms') }}" class="pautan" target="_blank">terma dan syarat</a>
                            (termasuk <a href="{{ route('refund') }}" class="pautan" target="_blank">polisi bayaran balik</a>)
                            serta membenarkan data saya diproses oleh ASUH (Galeri Ilmu Media Group) seperti dalam
                            <a href="{{ route('privacy') }}" class="pautan" target="_blank">notis privasi</a>.
                        </span>
                    </label>
                    @error('pdpa_consent') <p class="text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                @endguest

                <button type="submit" class="btn-utama w-full text-lg">Teruskan ke bayaran</button>
            </div>
        </form>
    </div>
</x-layouts.app>
