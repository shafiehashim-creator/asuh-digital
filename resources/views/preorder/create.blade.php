@php
    use App\Enums\AgeGroup;
    use App\Enums\PaymentMethod;
    use App\Support\Money;

    $user = auth()->user();
    $cardClass = 'rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)] sm:p-8';
@endphp

<x-layouts.app title="Tempah slot Langganan Tahunan">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">{{ __('Tempah slot Langganan Tahunan') }}</h1>

        @if ($plan)
            <div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl border-2 border-ink bg-kuning px-5 py-4">
                <div>
                    <p class="font-display text-lg font-bold">{{ __($plan->name) }}</p>
                    <p class="text-sm font-semibold">{{ __('12 bulan · sekali bayar, tiada caj automatik') }}</p>
                </div>
                <p class="font-display text-3xl font-extrabold">{{ Money::short($plan->price_sen) }}</p>
            </div>
        @endif

        <x-slot-counter :limit="$slotLimit" :remaining="$slotsRemaining" class="mt-4" />

        @if ($soldOut || ! $plan)
            <div class="{{ $cardClass }} mt-8">
                <p class="font-bold">{{ __('Maaf, semua slot early bird telah ditempah.') }}</p>
                <p class="mt-2 text-ink/80">{{ __('Terima kasih atas sokongan anda. Langganan Tahunan akan dijual pada harga biasa apabila platform dilancarkan.') }}</p>
                <a href="{{ route('home') }}" class="btn-gelap mt-4">{{ __('Kembali ke laman utama') }}</a>
            </div>
        @else
            @error('preorder')
                <div role="alert" class="mt-6 rounded-xl border-2 border-magenta bg-magenta/5 px-4 py-3 font-semibold text-magenta">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('preorder.store') }}" class="mt-8 space-y-8" novalidate>
                @csrf

                {{-- Account --}}
                <section class="{{ $cardClass }} space-y-5" aria-labelledby="akaun-heading">
                    <h2 id="akaun-heading" class="font-display text-xl font-bold text-plum">1. {{ __('Akaun anda') }}</h2>

                    @if ($user)
                        <p class="text-ink/80">{!! __('Anda log masuk sebagai :name (:email).', ['name' => '<strong>'.e($user->name).'</strong>', 'email' => e($user->email)]) !!}</p>
                        <x-field name="phone" label="No. telefon" type="tel" :value="$user->phone" autocomplete="tel" required hint="Contoh: 012-345 6789" />
                    @else
                        <p class="text-sm text-ink/80">
                            {{ __('Akaun ini untuk melihat pesanan dan, apabila platform dilancarkan, membaca bersama anak.') }}
                            {{ __('Sudah ada akaun?') }} <a href="{{ route('login') }}" class="pautan">{{ __('Log masuk') }}</a>
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

                {{-- Family --}}
                <section class="{{ $cardClass }} space-y-5" aria-labelledby="keluarga-heading">
                    <h2 id="keluarga-heading" class="font-display text-xl font-bold text-plum">2. {{ __('Tentang keluarga anda') }}</h2>
                    <p class="text-sm text-ink/80">{{ __('Membantu kami menyediakan buku yang sesuai. Kami tidak meminta nama, foto atau tarikh lahir anak.') }}</p>

                    <div>
                        <label for="state" class="label">{{ __('Negeri') }}</label>
                        <select id="state" name="state" required @class(['input', 'border-magenta!' => $errors->has('state')])>
                            <option value="">{{ __('Pilih negeri') }}</option>
                            @foreach (config('asuh.states') as $state)
                                <option value="{{ $state }}" @selected(old('state') === $state)>{{ __($state) }}</option>
                            @endforeach
                        </select>
                        @error('state') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                    </div>

                    <x-field name="children_count" label="Bilangan anak" type="number" min="1" max="10" inputmode="numeric" required class="max-w-32" />

                    <fieldset>
                        <legend class="label">{{ __('Umur anak-anak') }}</legend>
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach (AgeGroup::cases() as $group)
                                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border-2 border-ink/20 px-4 py-2.5 font-semibold has-checked:border-biru has-checked:bg-biru/5">
                                    <input type="radio" name="children_age_group" value="{{ $group->value }}" @checked(old('children_age_group') === $group->value) class="size-5 text-biru" required>
                                    {{ $group->getLabel() }}
                                </label>
                            @endforeach
                        </div>
                        @error('children_age_group') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                    </fieldset>
                </section>

                {{-- Payment method --}}
                <section class="{{ $cardClass }} space-y-4" aria-labelledby="bayaran-heading">
                    <h2 id="bayaran-heading" class="font-display text-xl font-bold text-plum">3. {{ __('Kaedah bayaran') }}</h2>

                    @foreach ([
                        [PaymentMethod::Chip, __('Bayar dalam talian sekarang. Slot ditempah selama :minutes minit.', ['minutes' => $reservationMinutes])],
                        [PaymentMethod::BankTransfer, __('Pindahkan ke akaun bank kami dan muat naik resit dalam masa :hours jam. Langganan diaktifkan selepas kami sahkan bayaran.', ['hours' => $bankTransferHours])],
                    ] as [$method, $note])
                        <label class="flex cursor-pointer gap-3 rounded-xl border-2 border-ink/20 p-4 has-checked:border-biru has-checked:bg-biru/5">
                            <input type="radio" name="payment_method" value="{{ $method->value }}" @checked(old('payment_method', PaymentMethod::Chip->value) === $method->value) class="mt-1 size-5 shrink-0 text-biru" required>
                            <span>
                                <span class="block font-bold">{{ $method->getLabel() }}</span>
                                <span class="block text-sm text-ink/80">{{ $note }}</span>
                            </span>
                        </label>
                    @endforeach
                    @error('payment_method') <p class="text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                </section>

                {{-- Consent and submit --}}
                <div class="space-y-5">
                    <label class="flex cursor-pointer gap-3">
                        <input type="checkbox" name="pdpa_consent" value="1" @checked(old('pdpa_consent')) class="mt-0.5 size-5 shrink-0 rounded border-2 border-ink/30 text-biru" required>
                        <span class="text-sm">
                            {!! __('Saya bersetuju dengan :terms (termasuk :refund) serta membenarkan data saya dan maklumat keluarga di atas diproses oleh ASUH (Galeri Ilmu Media Group) untuk pesanan ini dan akaun ASUH Digital, seperti dalam :privacy.', [
                                'terms' => '<a href="'.route('terms').'" class="pautan" target="_blank">'.e(__('terma dan syarat')).'</a>',
                                'refund' => '<a href="'.route('refund').'" class="pautan" target="_blank">'.e(__('polisi bayaran balik')).'</a>',
                                'privacy' => '<a href="'.route('privacy').'" class="pautan" target="_blank">'.e(__('notis privasi')).'</a>',
                            ]) !!}
                        </span>
                    </label>
                    @error('pdpa_consent') <p class="text-sm font-semibold text-magenta">{{ $message }}</p> @enderror

                    <button type="submit" class="btn-utama w-full text-lg">{{ __('Tempah slot & teruskan ke bayaran') }}</button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.app>
