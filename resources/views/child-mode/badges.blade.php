<x-layouts.child title="Lencana saya" :child="$child">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <a href="{{ route('child-mode.home') }}" class="pautan text-sm">← Rak buku</a>
        <h1 class="mt-2 font-display text-3xl font-extrabold text-magenta">Lencana {{ $child->nickname }}</h1>
        <p class="mt-1 font-semibold text-plum">Kumpul lencana dengan menjawab kuiz dan membaca setiap hari.</p>

        <ul class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
            @foreach (App\Enums\Badge::cases() as $badge)
                @php($row = $earned->get($badge->value))
                <li @class(['rounded-3xl border-2 p-4 text-center', 'border-ink bg-white shadow-[0_4px_0_var(--color-ink)]' => $row, 'border-ink/20 bg-white/50' => ! $row])>
                    <span @class(['block text-5xl', 'opacity-30 grayscale' => ! $row]) aria-hidden="true">{{ $badge->emoji() }}</span>
                    <p class="mt-2 font-display text-lg font-bold">{{ $badge->label() }}</p>
                    <p class="text-sm text-ink/80">{{ $badge->description() }}</p>
                    <p @class(['mt-2 text-sm font-bold', 'text-biru' => $row, 'text-ink/60' => ! $row])>
                        {{ $row ? 'Diperoleh '.$row->awarded_at->translatedFormat('j M Y') : 'Belum diperoleh' }}
                    </p>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts.child>
