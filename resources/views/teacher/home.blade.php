<x-layouts.app title="Portal guru">
    <div class="mx-auto max-w-4xl space-y-8 px-4 py-10">
        <div>
            <p class="font-display font-bold text-plum">{{ $institution->name }}</p>
            <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">Assalamualaikum, {{ $user->name }}</h1>
        </div>

        <section aria-labelledby="license-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="license-heading" class="font-display text-xl font-bold text-plum">Lesen institusi</h2>
            @if ($license)
                <p class="mt-2 text-lg">
                    <span class="font-bold text-biru">{{ $license->kind->getLabel() }}</span>, aktif sehingga
                    <strong>{{ $license->ends_on->translatedFormat('j F Y') }}</strong>, untuk sehingga {{ $license->max_students }} murid.
                </p>
                <p class="mt-1 text-ink/80">{{ $pupilCount }} murid didaftarkan · baki {{ max(0, $license->max_students - $pupilCount) }} tempat.</p>
                <p class="mt-1 text-ink/80">Waktu murid boleh membaca: <strong>{{ $license->schoolHoursLabel() ?? 'sepanjang masa' }}</strong></p>
                @if ($license->ends_on->lte(today()->addDays(30)))
                    <p role="status" class="mt-3 rounded-2xl bg-oren/15 p-3 font-semibold">
                        Lesen akan tamat dalam {{ (int) today()->diffInDays($license->ends_on) }} hari. Hubungi ASUH Digital untuk memperbaharui supaya murid boleh terus membaca.
                    </p>
                @endif
            @else
                <p class="mt-2 text-ink/80">Tiada lesen aktif. Murid tidak boleh membaca sehingga lesen diperbaharui. Hubungi pasukan ASUH Digital.</p>
            @endif
        </section>

        @if ($institution->parent_code && $institution->parent_discount_percent > 0)
            {{-- Reading at home is a family subscription; the school's code gives parents a discount. --}}
            <section aria-labelledby="parents-heading" class="rounded-3xl border-2 border-ink bg-lavender p-6 shadow-[0_4px_0_var(--color-ink)]">
                <h2 id="parents-heading" class="font-display text-xl font-bold text-plum">Untuk ibu bapa: baca di rumah</h2>
                <p class="mt-2 text-ink/80">Kongsi kod ini dengan ibu bapa murid. Mereka mendapat diskaun {{ $institution->parent_discount_percent }}% untuk Langganan Tahunan di rumah.</p>
                <p class="mt-3 inline-block rounded-2xl border-2 border-ink bg-kuning px-4 py-2 font-mono text-2xl font-extrabold tracking-widest">{{ $institution->parent_code }}</p>
                <label for="parent-message" class="label mt-4">Mesej untuk dihantar kepada ibu bapa (WhatsApp atau surat)</label>
                <textarea id="parent-message" rows="5" readonly class="input text-sm" onclick="this.select()">Assalamualaikum ibu bapa {{ $institution->name }}. Anak-anak kini membaca e-book Islamik ASUH Digital di sekolah. Untuk terus membaca di rumah, langgan ASUH Digital di {{ route('pass.show') }} dan masukkan kod sekolah {{ $institution->parent_code }} untuk diskaun {{ $institution->parent_discount_percent }}%. Jom Jadi Baik!</textarea>
            </section>
        @endif

        @if (session('status') === 'classroom-deleted')
            <p role="status" class="font-semibold text-biru">Kelas dan semua data muridnya telah dipadam.</p>
        @endif

        <section aria-labelledby="classes-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="classes-heading" class="font-display text-xl font-bold text-plum">{{ $user->isInstitutionAdmin() ? 'Semua kelas' : 'Kelas saya' }}</h2>
                <a href="{{ route('teacher.classrooms.create') }}" class="btn-utama">Cipta kelas</a>
            </div>

            @if ($classrooms->isEmpty())
                <p class="mt-3 text-ink/80">Belum ada kelas. Cipta kelas, tambah nama murid, dan berikan kod kelas kepada murid.</p>
            @else
                <ul class="mt-4 divide-y-2 divide-ink/10">
                    @foreach ($classrooms as $classroom)
                        <li>
                            <a href="{{ route('teacher.classrooms.show', $classroom) }}" class="flex flex-wrap items-center justify-between gap-3 py-4 hover:text-plum">
                                <span>
                                    <span class="block font-display text-lg font-bold">{{ $classroom->name }}</span>
                                    <span class="block text-sm text-ink/70">
                                        {{ $classroom->age_group->getLabel() }} · {{ $classroom->pupils_count }} murid
                                        @if ($user->isInstitutionAdmin()) · {{ $classroom->teacher?->name ?? 'Tiada guru' }} @endif
                                    </span>
                                </span>
                                <span class="rounded-full bg-kuning px-3 py-1 font-mono font-bold tracking-widest">{{ $classroom->code }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($user->isInstitutionAdmin())
            <section aria-labelledby="teachers-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                <h2 id="teachers-heading" class="font-display text-xl font-bold text-plum">Guru</h2>
                @if (session('status') === 'teacher-invited')
                    <p role="status" class="mt-2 text-sm font-semibold text-biru">Jemputan dihantar. Guru perlu membuka pautan dalam e-mel dalam masa 7 hari.</p>
                @elseif (session('status') === 'teacher-removed')
                    <p role="status" class="mt-2 text-sm font-semibold text-biru">Guru telah dikeluarkan. Kelasnya kini di bawah anda.</p>
                @endif

                <ul class="mt-3 divide-y-2 divide-ink/10">
                    @foreach ($teachers as $teacher)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <span>
                                <span class="block font-bold">{{ $teacher->name }}</span>
                                <span class="block text-sm text-ink/70">{{ $teacher->email }} · {{ $teacher->role->getLabel() }}</span>
                            </span>
                            @if ($teacher->id !== $user->id)
                                <form method="POST" action="{{ route('teacher.teachers.destroy', $teacher) }}"
                                    data-confirm="Keluarkan {{ $teacher->name }} daripada {{ $institution->name }}? Kelasnya akan dipindahkan kepada anda."
                                    onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="pautan inline-flex min-h-11 items-center text-sm">Keluarkan</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                    @foreach ($invitations as $invitation)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-ink/70">
                            <span>
                                <span class="block font-bold">{{ $invitation->name }}</span>
                                <span class="block text-sm">{{ $invitation->email }} · jemputan menunggu hingga {{ $invitation->expires_at->translatedFormat('j M') }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('teacher.teachers.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    @csrf
                    <x-field name="name" label="Nama guru" bag="invite" required />
                    <x-field name="email" label="E-mel guru" type="email" bag="invite" required />
                    <button type="submit" class="btn-gelap">Jemput guru</button>
                </form>
            </section>
        @endif
    </div>
</x-layouts.app>
