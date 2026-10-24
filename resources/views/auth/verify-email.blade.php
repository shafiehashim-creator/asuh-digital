<x-auth-card title="Sahkan e-mel anda">
    <p class="text-ink/80">
        Kami telah menghantar pautan pengesahan ke <strong>{{ auth()->user()->email }}</strong>.
        Klik pautan dalam e-mel itu untuk mengaktifkan akaun anda.
    </p>
    <p class="mt-3 text-sm text-ink/70">Tak jumpa? Semak folder Spam atau Promosi.</p>

    @if (session('status') === 'verification-link-sent')
        <div role="status" class="mt-4 rounded-xl border-2 border-biru/30 bg-biru/5 px-4 py-3 text-sm font-semibold text-biru">
            Pautan pengesahan baharu telah dihantar.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf
        <button type="submit" class="btn-utama w-full">Hantar semula pautan</button>
    </form>
</x-auth-card>
