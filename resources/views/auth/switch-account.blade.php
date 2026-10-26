<x-auth-card title="Tukar akaun">
    <p class="text-ink/80">
        {!! __('Anda sedang log masuk sebagai :name :email.', ['name' => '<strong class="text-ink">'.e($user->name).'</strong>', 'email' => '<span class="break-all">('.e($user->email).')</span>']) !!}
    </p>

    @if ($blocked)
        <p role="status" class="mt-4 rounded-xl border-2 border-oren/40 bg-oren/10 px-4 py-3 text-sm font-semibold">
            @if ($target === 'admin')
                {{ __('Akaun ini tidak boleh membuka panel admin.') }}
            @else
                {{ __('Akaun ini tidak boleh membuka portal guru.') }}
            @endif
            {{ __('Log keluar dahulu, kemudian log masuk dengan akaun yang betul.') }}
        </p>
    @else
        <p class="mt-4 text-sm text-ink/80">
            {{ __('Satu pelayar hanya boleh log masuk satu akaun pada satu masa. Untuk guna akaun lain, log keluar dahulu. Untuk guna dua akaun serentak, buka akaun kedua dalam tetingkap inkognito atau profil Chrome lain.') }}
        </p>
    @endif

    <form method="POST" action="{{ route('switch-account.logout') }}" class="mt-6">
        @csrf
        @if ($target)
            <input type="hidden" name="ke" value="{{ $target }}">
        @endif
        <button type="submit" class="btn-utama w-full">{{ __('Log keluar dan log masuk akaun lain') }}</button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ $continueUrl }}" class="pautan font-bold">{{ __('Teruskan sebagai :name', ['name' => $user->name]) }}</a>
    </p>
</x-auth-card>
