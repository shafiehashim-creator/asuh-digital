<x-auth-card title="Daftar akaun" intro="Akaun percuma untuk ibu bapa. Selepas mendaftar, anda boleh mencipta profil anak, membaca pratonton buku dan memilih langganan bila-bila masa.">
    <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
        @csrf

        <x-field name="name" label="Nama penuh" autocomplete="name" required autofocus />
        <x-field name="email" label="E-mel" type="email" autocomplete="email" required
            hint="Kami akan menghantar pautan untuk mengesahkan e-mel ini." />
        <x-field name="phone" label="No. telefon" type="tel" autocomplete="tel" required hint="Contoh: 012-345 6789" />
        <x-field name="password" label="Kata laluan" type="password" autocomplete="new-password" required
            hint="Sekurang-kurangnya 8 aksara, dengan huruf dan nombor." />
        <x-field name="password_confirmation" label="Taip semula kata laluan" type="password" autocomplete="new-password" required />

        <label class="flex cursor-pointer gap-3">
            <input type="checkbox" name="pdpa_consent" value="1" @checked(old('pdpa_consent')) class="mt-0.5 size-5 shrink-0 rounded border-2 border-ink/30 text-biru" required>
            <span class="text-sm">
                {!! __('Saya bersetuju dengan :terms serta membenarkan data saya dan maklumat anak yang saya masukkan diproses oleh ASUH (Galeri Ilmu Media Group), seperti dalam :privacy.', [
                    'terms' => '<a href="'.route('terms').'" class="pautan" target="_blank">'.e(__('terma dan syarat')).'</a>',
                    'privacy' => '<a href="'.route('privacy').'" class="pautan" target="_blank">'.e(__('notis privasi')).'</a>',
                ]) !!}
            </span>
        </label>
        @error('pdpa_consent') <p class="text-sm font-semibold text-magenta">{{ $message }}</p> @enderror

        <button type="submit" class="btn-utama w-full">{{ __('Daftar') }}</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink/80">
        {{ __('Sudah ada akaun?') }} <a href="{{ route('login') }}" class="pautan">{{ __('Log masuk') }}</a>
    </p>
</x-auth-card>
