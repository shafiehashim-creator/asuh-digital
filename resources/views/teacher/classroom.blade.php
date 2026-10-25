<x-layouts.app :title="$classroom->name">
    <div class="mx-auto max-w-4xl space-y-8 px-4 py-10">
        <div>
            <a href="{{ route('teacher.home') }}" class="pautan text-sm">← Portal guru</a>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-3xl font-extrabold text-magenta">{{ $classroom->name }}</h1>
                    <p class="text-ink/80">{{ $classroom->age_group->getLabel() }} · Guru: {{ $classroom->teacher?->name ?? '-' }}</p>
                </div>
                <a href="{{ route('teacher.classrooms.edit', $classroom) }}" class="pautan inline-flex min-h-11 items-center">Ubah kelas</a>
            </div>
        </div>

        @if (session('status') === 'pupils-added')
            <p role="status" class="font-semibold text-biru">{{ session('added') }} murid ditambah.</p>
        @elseif (session('status') === 'pupil-deleted')
            <p role="status" class="font-semibold text-biru">Murid dan semua rekod bacaannya telah dipadam.</p>
        @elseif (session('status') === 'code-changed')
            <p role="status" class="font-semibold text-biru">Kod kelas baharu dijana. Kod lama tidak boleh digunakan lagi.</p>
        @endif

        {{-- How pupils get in --}}
        <section aria-labelledby="code-heading" class="rounded-3xl border-2 border-ink bg-kuning p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="code-heading" class="font-display text-xl font-bold">Kod kelas</h2>
            <p class="mt-2 font-mono text-5xl font-extrabold tracking-[0.3em]">{{ $classroom->code }}</p>
            <p class="mt-3">Murid buka <strong>{{ preg_replace('#^https?://#', '', url('/kelas')) }}</strong>, masukkan kod ini, dan tekan nama sendiri.</p>
            <form method="POST" action="{{ route('teacher.classrooms.code', $classroom) }}" class="mt-3"
                data-confirm="Jana kod baharu? Murid perlu menggunakan kod baharu selepas ini."
                onsubmit="return confirm(this.dataset.confirm)">
                @csrf
                <button type="submit" class="pautan inline-flex min-h-11 items-center text-sm">Jana kod baharu</button>
            </form>
        </section>

        {{-- Pupils --}}
        <section aria-labelledby="pupils-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="pupils-heading" class="font-display text-xl font-bold text-plum">Murid ({{ $classroom->pupils->count() }})</h2>

            @if ($classroom->pupils->isEmpty())
                <p class="mt-2 text-ink/80">Belum ada murid. Tambah nama panggilan murid di bawah.</p>
            @else
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($classroom->pupils as $pupil)
                        <li class="flex items-center gap-3 rounded-2xl border-2 border-ink/15 p-3">
                            <x-child-avatar :avatar="$pupil->avatar" />
                            <p class="min-w-0 flex-1 truncate font-display text-lg font-bold">{{ $pupil->nickname }}</p>
                            <form method="POST" action="{{ route('teacher.pupils.destroy', [$classroom, $pupil]) }}"
                                data-confirm="Padam {{ $pupil->nickname }} dan semua rekod bacaannya?"
                                onsubmit="return confirm(this.dataset.confirm)">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="pautan inline-flex min-h-11 items-center text-sm" aria-label="Padam {{ $pupil->nickname }}">Padam</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('teacher.pupils.store', $classroom) }}" class="mt-6 border-t-2 border-ink/10 pt-5">
                @csrf
                <label for="names" class="label">Tambah murid (satu nama panggilan setiap baris)</label>
                <textarea id="names" name="names" rows="5" class="input" placeholder="Aisyah&#10;Muhammad&#10;Nur Iman" @error('names') aria-invalid="true" @enderror>{{ old('names') }}</textarea>
                <p class="mt-1 text-sm text-ink/70">
                    Gunakan nama panggilan sahaja, bukan nama penuh. Nama mesti berbeza dalam kelas ini.
                    @if ($license) Baki tempat dalam lesen: <strong>{{ $seatsLeft }}</strong>. @endif
                </p>
                @error('names') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                <button type="submit" class="btn-gelap mt-3">Tambah murid</button>
            </form>
        </section>
    </div>
</x-layouts.app>
