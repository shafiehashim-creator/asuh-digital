<x-layouts.child :title="$classroom->name" exit-class>
    <div class="mx-auto max-w-4xl px-4 py-8">
        <p class="font-display font-bold text-plum">{{ $classroom->institution->name }}</p>
        <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">{{ $classroom->name }}</h1>

        @if (! $licensed)
            <p class="mt-6 rounded-3xl border-2 border-ink bg-white p-6 text-lg font-semibold">Kelas ini belum boleh membaca buat masa ini. Tanya guru kamu.</p>
        @elseif (! $open)
            <div class="mt-6 rounded-3xl border-2 border-ink bg-white p-6">
                <p class="text-4xl" aria-hidden="true">🏫</p>
                <p class="mt-2 font-display text-xl font-bold">Kelas ASUH dibuka pada waktu sekolah sahaja.</p>
                @if ($hours) <p class="mt-1 text-ink/80">Waktu kelas: {{ $hours }}</p> @endif
                <p class="mt-3 font-semibold text-plum">Nak sambung baca di rumah? Minta ibu atau ayah langgan ASUH Digital.</p>
            </div>
        @elseif ($classroom->pupils->isEmpty())
            <p class="mt-6 rounded-3xl border-2 border-ink bg-white p-6 text-lg font-semibold">Belum ada nama dalam kelas ini. Tanya guru kamu.</p>
        @else
            <p class="mt-1 text-lg font-semibold text-plum">Tekan nama kamu.</p>
            <ul class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($classroom->pupils as $pupil)
                    <li>
                        <form method="POST" action="{{ route('pupil.choose', $pupil) }}">
                            @csrf
                            <button type="submit" class="flex w-full flex-col items-center gap-2 rounded-3xl border-2 border-ink bg-white p-4 shadow-[0_3px_0_var(--color-ink)] hover:bg-kuning">
                                <x-child-avatar :avatar="$pupil->avatar" size="lg" />
                                <span class="font-display text-lg font-bold">{{ $pupil->nickname }}</span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <a href="{{ route('pupil.join') }}" class="pautan mt-8 inline-flex min-h-11 items-center">Kod kelas lain</a>
    </div>
</x-layouts.child>
