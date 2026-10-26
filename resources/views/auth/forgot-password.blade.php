<x-auth-card title="Lupa kata laluan" intro="Masukkan e-mel akaun anda. Kami akan hantar pautan untuk menetapkan kata laluan baharu.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-field name="email" label="E-mel" type="email" autocomplete="email" required autofocus />

        <button type="submit" class="btn-utama w-full">{{ __('Hantar pautan') }}</button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="pautan">{{ __('Kembali ke log masuk') }}</a>
    </p>
</x-auth-card>
