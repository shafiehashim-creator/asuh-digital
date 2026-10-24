<x-layouts.app title="Permohonan diterima">
    <div class="mx-auto max-w-xl px-4 py-16 text-center">
        <div class="rounded-3xl border-2 border-ink bg-white p-8 shadow-[0_4px_0_var(--color-ink)]">
            <h1 class="font-display text-3xl font-extrabold text-magenta">Terima kasih!</h1>
            <p class="mt-3 text-lg">
                Permohonan pilot
                @if (session('pilot_institution'))
                    untuk <strong>{{ session('pilot_institution') }}</strong>
                @endif
                telah diterima.
            </p>
            <p class="mt-3 text-ink/80">
                Pasukan kami akan menghubungi anda untuk mengatur pilot percuma selama {{ $pilotDays }} hari.
                Salinan permohonan telah dihantar ke e-mel anda.
            </p>
            <a href="{{ route('home') }}" class="btn-utama mt-6">Kembali ke laman utama</a>
        </div>
    </div>
</x-layouts.app>
