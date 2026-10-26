<x-auth-card title="Log masuk" intro="Log masuk untuk melihat pesanan dan Langganan Tahunan anda.">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-field name="email" label="E-mel" type="email" autocomplete="email" required autofocus />
        <x-field name="password" label="Kata laluan" type="password" autocomplete="current-password" required />

        <div class="flex items-center justify-between gap-4">
            <label class="flex min-h-11 items-center gap-2 text-sm font-semibold">
                <input type="checkbox" name="remember" class="size-5 rounded border-2 border-ink/30 text-biru">
                {{ __('Ingat saya') }}
            </label>
            <a href="{{ route('password.request') }}" class="pautan text-sm">{{ __('Lupa kata laluan?') }}</a>
        </div>

        <button type="submit" class="btn-utama w-full">{{ __('Log masuk') }}</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink/80">
        {{ __('Belum ada akaun?') }} <a href="{{ route('register') }}" class="pautan font-bold">{{ __('Daftar percuma') }}</a>
    </p>
</x-auth-card>
