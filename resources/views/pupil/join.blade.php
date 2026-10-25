<x-layouts.child title="Masuk kelas">
    <div class="mx-auto max-w-md px-4 py-10">
        <div class="rounded-3xl border-2 border-ink bg-white p-6 text-center shadow-[0_4px_0_var(--color-ink)]">
            <h1 class="font-display text-3xl font-extrabold text-magenta">Jom masuk kelas!</h1>
            <p class="mt-2 text-ink/80">Taip kod kelas yang diberi oleh guru kamu.</p>

            @if (session('status') === 'pupil-ended')
                <p role="status" class="mt-4 rounded-2xl bg-oren/15 p-3 font-semibold">Akses kelas sudah tamat. Tanya guru kamu.</p>
            @elseif (session('status') === 'pupil-after-hours')
                <p role="status" class="mt-4 rounded-2xl bg-oren/15 p-3 font-semibold">Waktu kelas dah tamat. Nak sambung baca di rumah? Minta ibu atau ayah langgan ASUH Digital.</p>
            @endif

            <form method="POST" action="{{ route('pupil.code') }}" class="mt-6 space-y-4">
                @csrf
                <label for="code" class="sr-only">Kod kelas</label>
                <input id="code" name="code" type="text" value="{{ old('code') }}" maxlength="7" autocomplete="off" autocapitalize="characters"
                    spellcheck="false" required autofocus placeholder="AB7K2Q"
                    class="input text-center font-mono text-3xl font-extrabold tracking-[0.3em] uppercase" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                @error('code') <p id="code-error" class="text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                <button type="submit" class="btn-utama w-full justify-center text-lg">Masuk</button>
            </form>
        </div>
    </div>
</x-layouts.child>
