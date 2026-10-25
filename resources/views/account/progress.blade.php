<x-layouts.account title="Pencapaian" heading="Pencapaian" :intro="'Bacaan minggu ini (sejak Isnin, '.$from->translatedFormat('j M').') dan lencana setiap anak.'" :$activePass :$pendingPass :$shelfOpen :$plusUpgradePrice>
    @forelse ($weeks as $week)
        @php
            $child = $week['child'];
            $earned = $child->badges->keyBy(fn ($row) => $row->badge->value);
        @endphp

        <section aria-labelledby="child-{{ $child->id }}-heading" class="rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)] sm:p-6">
            <div class="flex items-center gap-3">
                <x-child-avatar :avatar="$child->avatar" />
                <div class="min-w-0">
                    <h2 id="child-{{ $child->id }}-heading" class="truncate font-display text-xl font-bold text-plum">{{ $child->nickname }}</h2>
                    <p class="text-sm text-ink/70">{{ $child->age_group->getLabel() }} · {{ $earned->count() }} / {{ count($allBadges) }} lencana</p>
                </div>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([['Minit membaca', $week['minutes']], ['Hari membaca', $week['days']], ['Buku dibaca', count($week['books'])], ['Kuiz lulus', count($week['quizzes'])]] as [$label, $value])
                    <div class="rounded-2xl bg-latar p-3">
                        <dt class="text-xs font-extrabold tracking-wide text-ink/70 uppercase">{{ $label }}</dt>
                        <dd class="font-display text-2xl font-extrabold text-plum">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($week['video_minutes'] > 0)
                <p class="mt-3 text-sm text-ink/80">Menonton video animasi: <strong>{{ $week['video_minutes'] }} minit</strong> minggu ini.</p>
            @endif

            @if ($week['books'])
                <p class="mt-3 text-sm text-ink/80"><strong>Dibaca minggu ini:</strong> {{ implode(', ', $week['books']) }}</p>
            @endif

            <h3 class="mt-5 font-display font-bold">Lencana</h3>
            <ul class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($allBadges as $badge)
                    @php($row = $earned->get($badge->value))
                    <li @class(['flex items-center gap-3 rounded-2xl border-2 p-3', 'border-oren bg-oren/10' => $row, 'border-ink/10 opacity-60' => ! $row])>
                        <span class="text-2xl" aria-hidden="true">{{ $row ? $badge->emoji() : '🔒' }}</span>
                        <span class="min-w-0">
                            <span class="block text-sm leading-tight font-bold">{{ $badge->label() }}</span>
                            <span class="block text-xs text-ink/70">{{ $row ? 'Diperoleh '.$row->awarded_at->translatedFormat('j M Y') : $badge->description() }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <section class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <p class="text-ink/80">Tambah profil anak dahulu. Bacaan, kuiz dan lencana dalam Mod Anak akan dipaparkan di sini.</p>
            <a href="{{ route('children.create') }}" class="btn-gelap mt-4">Tambah profil anak</a>
        </section>
    @endforelse
</x-layouts.account>
