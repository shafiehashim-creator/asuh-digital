<x-layouts.child title="Pintu ibu bapa">
    <div class="mx-auto max-w-md px-4 py-10">
        <div class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h1 class="font-display text-2xl font-extrabold text-plum">Untuk ibu bapa sahaja</h1>

            <form method="POST" action="{{ route('child-mode.leave') }}" class="mt-4 space-y-4">
                @csrf
                @if ($usesPin)
                    <x-field name="answer" label="Masukkan PIN ibu bapa (4 digit)" type="password" inputmode="numeric"
                        pattern="[0-9]*" maxlength="4" autocomplete="off" required autofocus />
                @else
                    <p class="text-ink/80">Jawab soalan ini untuk keluar dari Mod Anak:</p>
                    <p class="font-display text-xl font-bold">{{ $question }}</p>
                    <x-field name="answer" label="Jawapan (dalam nombor)" inputmode="numeric" pattern="[0-9]*" maxlength="4"
                        autocomplete="off" required autofocus />
                @endif

                <button type="submit" class="btn-gelap">Keluar dari Mod Anak</button>
            </form>
        </div>

        <a href="{{ route('child-mode.home') }}" class="btn-utama mt-6">← Kembali ke rak buku</a>
    </div>
</x-layouts.child>
