<x-auth-card title="Tetapkan kata laluan baharu">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-field name="email" label="E-mel" type="email" :value="$request->email" autocomplete="email" required />
        <x-field name="password" label="Kata laluan baharu" type="password" autocomplete="new-password" required autofocus
            hint="Sekurang-kurangnya 8 aksara, dengan huruf dan nombor." />
        <x-field name="password_confirmation" label="Taip semula kata laluan" type="password" autocomplete="new-password" required />

        <button type="submit" class="btn-utama w-full">{{ __('Simpan kata laluan') }}</button>
    </form>
</x-auth-card>
