<x-auth-card title="Sahkan kata laluan" intro="Untuk keselamatan, sila masukkan kata laluan anda sebelum meneruskan.">
    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
        @csrf

        <x-field name="password" label="Kata laluan" type="password" autocomplete="current-password" required autofocus />

        <button type="submit" class="btn-utama w-full">Sahkan</button>
    </form>
</x-auth-card>
