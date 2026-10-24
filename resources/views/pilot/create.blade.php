@php
    use App\Enums\AgeGroup;
    use App\Enums\InstitutionType;

    $cardClass = 'rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)] sm:p-8';
@endphp

<x-layouts.app title="Mohon pilot institusi">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">Mohon pilot percuma untuk institusi anda</h1>
        <p class="mt-3 text-ink/80">
            Untuk tadika, sekolah rendah agama, KAFA dan tahfiz. Cuba ASUH Digital bersama murid anda secara percuma selama
            <strong>{{ $pilotDays }} hari</strong>. Isi borang ini dan pasukan kami akan menghubungi anda.
        </p>

        <form method="POST" action="{{ route('pilot.store') }}" class="mt-8 space-y-8" novalidate>
            @csrf

            {{-- Honeypot for spam bots; hidden from people and screen readers. --}}
            <div class="hidden" aria-hidden="true">
                <label for="website">Laman web</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <section class="{{ $cardClass }} space-y-5" aria-labelledby="institusi-heading">
                <h2 id="institusi-heading" class="font-display text-xl font-bold text-plum">1. Institusi</h2>

                <x-field name="institution_name" label="Nama institusi" autocomplete="organization" required />

                <fieldset>
                    <legend class="label">Jenis institusi</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach (InstitutionType::cases() as $type)
                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border-2 border-ink/20 px-4 py-2.5 font-semibold has-checked:border-biru has-checked:bg-biru/5">
                                <input type="radio" name="institution_type" value="{{ $type->value }}" @checked(old('institution_type') === $type->value) class="size-5 text-biru" required>
                                {{ $type->getLabel() }}
                            </label>
                        @endforeach
                    </div>
                    @error('institution_type') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                </fieldset>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="state" class="label">Negeri</label>
                        <select id="state" name="state" required @class(['input', 'border-magenta!' => $errors->has('state')])>
                            <option value="">Pilih negeri</option>
                            @foreach (config('asuh.states') as $state)
                                <option value="{{ $state }}" @selected(old('state') === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                        @error('state') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                    </div>
                    <x-field name="district" label="Daerah" required />
                </div>

                <x-field name="student_count" label="Anggaran bilangan murid" type="number" min="1" inputmode="numeric" required class="max-w-40" />

                <fieldset>
                    <legend class="label">Umur murid</legend>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach (AgeGroup::cases() as $group)
                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border-2 border-ink/20 px-4 py-2.5 font-semibold has-checked:border-biru has-checked:bg-biru/5">
                                <input type="radio" name="age_group" value="{{ $group->value }}" @checked(old('age_group') === $group->value) class="size-5 text-biru" required>
                                {{ $group->getLabel() }}
                            </label>
                        @endforeach
                    </div>
                    @error('age_group') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                </fieldset>
            </section>

            <section class="{{ $cardClass }} space-y-5" aria-labelledby="pegawai-heading">
                <h2 id="pegawai-heading" class="font-display text-xl font-bold text-plum">2. Pegawai untuk dihubungi</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="contact_name" label="Nama" autocomplete="name" required />
                    <x-field name="contact_position" label="Jawatan" required hint="Contoh: Guru Besar, Pengetua" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="email" label="E-mel" type="email" autocomplete="email" required />
                    <x-field name="phone" label="No. telefon" type="tel" autocomplete="tel" required hint="Bimbit atau pejabat" />
                </div>

                <div>
                    <label for="notes" class="label">Catatan (pilihan)</label>
                    <textarea id="notes" name="notes" rows="4" maxlength="2000" class="input"
                        placeholder="Contoh: waktu sesuai untuk dihubungi, bilangan kelas">{{ old('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                </div>
            </section>

            <div class="space-y-5">
                <label class="flex cursor-pointer gap-3">
                    <input type="checkbox" name="pdpa_consent" value="1" @checked(old('pdpa_consent')) class="mt-0.5 size-5 shrink-0 rounded border-2 border-ink/30 text-biru" required>
                    <span class="text-sm">
                        Saya bersetuju maklumat di atas digunakan oleh ASUH (Galeri Ilmu Media Group) untuk menghubungi saya
                        tentang program pilot, seperti dalam <a href="{{ route('privacy') }}" class="pautan" target="_blank">notis privasi</a>.
                    </span>
                </label>
                @error('pdpa_consent') <p class="text-sm font-semibold text-magenta">{{ $message }}</p> @enderror

                <button type="submit" class="btn-utama w-full text-lg">Hantar permohonan</button>
            </div>
        </form>
    </div>
</x-layouts.app>
