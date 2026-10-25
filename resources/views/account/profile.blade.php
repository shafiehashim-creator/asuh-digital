<x-layouts.account title="Profil dan keselamatan" heading="Profil dan keselamatan" :$activePass :$pendingPass :$shelfOpen :$plusUpgradePrice>
    <div class="grid gap-6 xl:grid-cols-2">
        {{-- Profile --}}
        <section aria-labelledby="profile-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="profile-heading" class="font-display text-xl font-bold text-plum">Maklumat peribadi</h2>

            @if (session('status') === 'profile-information-updated')
                <p role="status" class="mt-3 text-sm font-semibold text-biru">Maklumat disimpan.</p>
            @endif

            <form method="POST" action="{{ route('user-profile-information.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <x-field name="name" label="Nama" :value="$user->name" bag="updateProfileInformation" autocomplete="name" required />
                <x-field name="email" label="E-mel" type="email" :value="$user->email" bag="updateProfileInformation" autocomplete="email" required
                    hint="Jika ditukar, anda perlu mengesahkan e-mel baharu." />
                <x-field name="phone" label="No. telefon" type="tel" :value="$user->phone" bag="updateProfileInformation" autocomplete="tel" required
                    hint="Contoh: 012-345 6789" />

                <button type="submit" class="btn-gelap">Simpan</button>
            </form>
        </section>

        {{-- Password --}}
        <section aria-labelledby="password-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="password-heading" class="font-display text-xl font-bold text-plum">Tukar kata laluan</h2>

            @if (session('status') === 'password-updated')
                <p role="status" class="mt-3 text-sm font-semibold text-biru">Kata laluan ditukar.</p>
            @endif

            <form method="POST" action="{{ route('user-password.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <x-field name="current_password" label="Kata laluan semasa" type="password" bag="updatePassword" autocomplete="current-password" required />
                <x-field name="password" label="Kata laluan baharu" type="password" bag="updatePassword" autocomplete="new-password" required
                    hint="Sekurang-kurangnya 8 aksara, dengan huruf dan nombor." />
                <x-field name="password_confirmation" label="Taip semula kata laluan baharu" type="password" bag="updatePassword" autocomplete="new-password" required />

                <button type="submit" class="btn-gelap">Tukar kata laluan</button>
            </form>
        </section>
    </div>

    {{-- Weekly reading report e-mail --}}
    @if ($shelfOpen)
        <section aria-labelledby="weekly-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="weekly-heading" class="font-display text-xl font-bold text-plum">Laporan bacaan mingguan</h2>
            @if (session('status') === 'weekly-report-saved')
                <p role="status" class="mt-2 text-sm font-semibold text-biru">Tetapan laporan disimpan.</p>
            @endif

            <form method="POST" action="{{ route('weekly-report.update') }}" class="mt-2">
                @csrf
                @method('PUT')
                <input type="hidden" name="weekly_report" value="0">
                <label class="flex min-h-11 cursor-pointer items-center gap-3">
                    <input type="checkbox" name="weekly_report" value="1" class="size-5 rounded text-biru" @checked($user->weekly_report)>
                    <span>Hantar e-mel setiap Isnin: minit membaca, buku, kuiz dan lencana anak minggu lalu.</span>
                </label>
                <button type="submit" class="btn-gelap mt-2">Simpan</button>
            </form>
        </section>
    @endif
</x-layouts.account>
