<x-layouts.app title="Jemputan institusi">
    <div class="mx-auto max-w-lg px-4 py-10">
        @if (! $invitation)
            <div class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                <h1 class="font-display text-2xl font-extrabold text-magenta">Jemputan tidak sah</h1>
                <p class="mt-2 text-ink/80">Pautan ini sudah digunakan atau telah tamat tempoh. Minta pentadbir institusi anda menghantar jemputan baharu.</p>
            </div>
        @else
            <div class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                <h1 class="font-display text-2xl font-extrabold text-magenta">Sertai {{ $invitation->institution->name }}</h1>
                <p class="mt-2 text-ink/80">Anda dijemput sebagai <strong>{{ $invitation->role->getLabel() }}</strong>. Tetapkan kata laluan untuk log masuk dengan e-mel <strong>{{ $invitation->email }}</strong>.</p>

                @if ($taken || $errors->has('email'))
                    <p role="alert" class="mt-4 rounded-2xl bg-magenta/10 p-3 font-semibold text-magenta">E-mel ini sudah mempunyai akaun ASUH Digital. Hubungi kami untuk menyambungkannya ke institusi anda.</p>
                @else
                    <form method="POST" action="{{ route('invitation.accept', $token) }}" class="mt-6 space-y-4">
                        @csrf
                        <x-field name="name" label="Nama penuh" :value="$invitation->name" autocomplete="name" required />
                        <x-field name="password" label="Kata laluan" type="password" autocomplete="new-password" required
                            hint="Sekurang-kurangnya 8 aksara, dengan huruf dan nombor." />
                        <x-field name="password_confirmation" label="Taip semula kata laluan" type="password" autocomplete="new-password" required />
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="terms" value="1" class="mt-1 size-5 rounded text-biru" required>
                            <span class="text-sm">Saya bersetuju dengan <a href="{{ route('terms') }}" class="pautan" target="_blank">terma</a> dan <a href="{{ route('privacy') }}" class="pautan" target="_blank">notis privasi</a> ASUH Digital.</span>
                        </label>
                        @error('terms') <p class="text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                        <button type="submit" class="btn-utama">Terima jemputan</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</x-layouts.app>
