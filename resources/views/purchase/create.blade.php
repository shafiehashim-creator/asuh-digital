@php
    use App\Enums\PaymentMethod;
    use App\Models\Setting;

    $user = auth()->user();
    $cardClass = 'rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)] sm:p-8';
    $bankHours = (int) Setting::get('bank_transfer_reservation_hours');
    $schoolCode ??= false;
    $periods ??= [];
    $chosen = old('tempoh', request('tempoh', $periods[0]['value'] ?? null));
@endphp

<x-layouts.app :title="$title">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">{{ $title }}</h1>

        @unless ($periods)
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
        @endunless

        @if ($note)
            <p role="note" class="mt-4 rounded-xl border-2 border-biru/30 bg-biru/5 px-4 py-3 text-sm font-semibold text-biru">{{ $note }}</p>
        @endif

        @error('purchase')
            <div role="alert" class="mt-6 rounded-xl border-2 border-magenta bg-magenta/5 px-4 py-3 font-semibold text-magenta">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ $action }}" class="mt-8 space-y-8" novalidate>
            @csrf

            @if ($periods)
                {{-- Yearly or monthly: yearly comes first, chosen by default and marked the better deal. --}}
                <fieldset class="space-y-3">
                    <legend class="sr-only">{{ __('Pilih tempoh langganan') }}</legend>
                    @foreach ($periods as $period)
                        <label @class([
                            'relative flex cursor-pointer items-center gap-4 rounded-3xl border-2 border-ink/20 bg-white p-5 transition has-checked:border-ink has-checked:bg-kuning has-checked:shadow-[0_4px_0_var(--color-ink)]',
                            'pt-7' => $period['badge'],
                        ])>
                            @if ($period['badge'])
                                <span class="absolute -top-3 left-5 rounded-full border-2 border-ink bg-oren px-3 py-0.5 text-xs font-extrabold">★ {{ $period['badge'] }}</span>
                            @endif
                            <input type="radio" name="tempoh" value="{{ $period['value'] }}" @checked($chosen === $period['value']) class="size-6 shrink-0 text-biru">
                            <span class="min-w-0 flex-1">
                                <span class="block font-display text-xl font-extrabold">{{ $period['label'] }}</span>
                                <span class="block text-sm font-semibold text-ink/75">{{ $period['line'] }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                @if ($period['was'] ?? null)
                                    <span class="block text-sm font-bold text-ink/60 line-through">{{ $period['was'] }}</span>
                                @endif
                                <span class="font-display text-3xl font-extrabold">{{ $period['price'] }}</span>
                            </span>
                        </label>
                    @endforeach
                    <ul class="grid gap-1 px-2 pt-1 text-sm font-semibold text-ink/80 sm:grid-cols-3">
                        @foreach ($details as $detail)
                            <li>✓ {{ $detail }}</li>
                        @endforeach
                    </ul>
                    @error('tempoh') <p class="text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                </fieldset>
            @endif

            <section class="{{ $cardClass }} space-y-5" aria-labelledby="akaun-heading">
                <h2 id="akaun-heading" class="font-display text-xl font-bold text-plum">1. {{ __('Akaun anda') }}</h2>

                @if ($user)
                    <p class="text-ink/80">{!! __('Anda log masuk sebagai :name (:email).', ['name' => '<strong>'.e($user->name).'</strong>', 'email' => e($user->email)]) !!}</p>
                @else
                    <p class="text-sm text-ink/80">
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

            @if ($promoCode ?? false)
                {{-- One code per order: a coupon, a school's parent code or an affiliate code. --}}
                <details class="{{ $cardClass }}" @if (old('promo_code') || $errors->has('promo_code')) open @endif>
                    <summary class="cursor-pointer font-display text-lg font-bold text-plum">{{ __('Ada kod promosi atau kod sekolah?') }}</summary>
                    <div class="mt-4">
                        <x-field name="promo_code" label="Kod promosi" autocomplete="off" class="uppercase" :value="old('promo_code', request('kod'))"
                            hint="Kod kupon, kod sekolah daripada tadika atau sekolah anak anda, atau kod rakan affiliate. Satu kod setiap pesanan. Diskaun dikira pada halaman bayaran." />
                    </div>
                </details>
            @endif

            <section class="{{ $cardClass }} space-y-4" aria-labelledby="bayaran-heading">
                <h2 id="bayaran-heading" class="font-display text-xl font-bold text-plum">2. {{ __('Kaedah bayaran') }}</h2>

                @foreach ([
                    [PaymentMethod::Chip, __('Bayar dalam talian sekarang dan akses dibuka serta-merta.')],
                    [PaymentMethod::BankTransfer, __('Pindahkan ke akaun bank kami dan muat naik resit. Akses dibuka selepas kami sahkan bayaran.')],
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
                            {!! __('Saya bersetuju dengan :terms (termasuk :refund) serta membenarkan data saya diproses oleh ASUH (Galeri Ilmu Media Group) seperti dalam :privacy.', [
                                'terms' => '<a href="'.route('terms').'" class="pautan" target="_blank">'.e(__('terma dan syarat')).'</a>',
                                'refund' => '<a href="'.route('refund').'" class="pautan" target="_blank">'.e(__('polisi bayaran balik')).'</a>',
                                'privacy' => '<a href="'.route('privacy').'" class="pautan" target="_blank">'.e(__('notis privasi')).'</a>',
                            ]) !!}
                        </span>
                    </label>
                    @error('pdpa_consent') <p class="text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                @endguest

                <button type="submit" class="btn-utama w-full text-lg">{{ __('Teruskan ke bayaran') }}</button>
            </div>
        </form>
    </div>
</x-layouts.app>
