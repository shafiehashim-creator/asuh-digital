<x-layouts.account title="Pencapaian" heading="Pencapaian" :intro="'Bacaan minggu ini (sejak Isnin, '.$from->translatedFormat('j M').') dan lencana setiap anak.'" :$activePass :$pendingPass :$shelfOpen :$plusUpgradePrice>
    @if ($message = ['promise-saved' => 'Janji ganjaran disimpan.', 'promise-fulfilled' => 'Alhamdulillah, janji ditunaikan.', 'promise-deleted' => 'Janji dipadam.'][session('status')] ?? null)
        <p role="status" class="rounded-2xl bg-biru/10 px-4 py-3 text-sm font-bold text-biru">{{ $message }}</p>
    @endif

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

            {{-- Reward promises: the parent's own deal with the child --}}
            @php($bag = 'promise'.$child->id)
            @php($choices = $promises->promisable($child))
            <div class="mt-5 rounded-2xl border-2 border-dashed border-oren bg-oren/5 p-4">
                <h3 class="font-display font-bold">🎁 Janji ganjaran</h3>
                <p class="text-sm text-ink/75">Janjikan sesuatu kepada {{ $child->nickname }} jika dia mendapat lencana tertentu. Contoh: lencana Seminggu Membaca → aiskrim. {{ $child->nickname }} nampak janji ini dalam Mod Anak, dan kami e-mel anda bila ia tercapai.</p>

                @if ($child->rewardPromises->isNotEmpty())
                    <ul class="mt-3 space-y-2">
                        @foreach ($child->rewardPromises->sortBy('fulfilled_at') as $promise)
                            <li class="flex flex-wrap items-center gap-3 rounded-2xl bg-white p-3">
                                <span class="text-2xl" aria-hidden="true">{{ $promise->badge->emoji() }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold">{{ $promise->badge->label() }} → {{ $promise->reward }}</span>
                                    <span @class(['block text-xs font-bold', 'text-ink/60' => ! $promise->isAchieved(), 'text-magenta' => $promise->isAchieved() && ! $promise->isFulfilled(), 'text-biru' => $promise->isFulfilled()])>
                                        @if ($promise->isFulfilled())
                                            ✓ Ditunaikan {{ $promise->fulfilled_at->translatedFormat('j M Y') }}
                                        @elseif ($promise->isAchieved())
                                            🎉 Tercapai {{ $promise->achieved_at->translatedFormat('j M Y') }}! Tunaikan janji anda.
                                        @else
                                            Dalam usaha: {{ $promise->badge->description() }}
                                        @endif
                                    </span>
                                </span>
                                @if ($promise->isAchieved() && ! $promise->isFulfilled())
                                    <form method="POST" action="{{ route('reward-promises.fulfil', $promise) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn-utama min-h-10 px-4 text-sm">Sudah ditunaikan</button>
                                    </form>
                                @endif
                                @unless ($promise->isFulfilled())
                                    <form method="POST" action="{{ route('reward-promises.destroy', $promise) }}" onsubmit="return confirm('Padam janji ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="min-h-10 rounded-full px-3 text-sm font-bold text-ink/60 hover:text-magenta">Padam</button>
                                    </form>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($choices && $promises->canAddMore($child))
                    <form method="POST" action="{{ route('reward-promises.store', $child) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        @csrf
                        <label class="block text-sm font-bold">Jika {{ $child->nickname }} dapat lencana
                            <select name="badge" class="input mt-1" required>
                                @foreach ($choices as $badge)
                                    <option value="{{ $badge->value }}" @selected(old('badge') === $badge->value)>{{ $badge->emoji() }} {{ $badge->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-bold">Ganjaran daripada ibu ayah
                            <input type="text" name="reward" maxlength="120" required placeholder="Contoh: aiskrim, ke taman, buku baharu" value="{{ old('reward') }}" class="input mt-1">
                        </label>
                        <button type="submit" class="btn-gelap min-h-11 text-sm">Simpan janji</button>
                    </form>
                    @if ($errors->{$bag}->any())
                        <p class="mt-2 text-sm font-semibold text-magenta">{{ $errors->{$bag}->first() }}</p>
                    @endif
                @endif
            </div>
        </section>
    @empty
        <section class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <p class="text-ink/80">Tambah profil anak dahulu. Bacaan, kuiz dan lencana dalam Mod Anak akan dipaparkan di sini.</p>
            <a href="{{ route('children.create') }}" class="btn-gelap mt-4">Tambah profil anak</a>
        </section>
    @endforelse
</x-layouts.account>
