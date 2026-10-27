@php
    use App\Enums\AgeGroup;
    use App\Enums\ChildAvatar;

    $editing = $child->exists;
    $selectedAge = old('age_group', $child->age_group?->value);
    $selectedAvatar = old('avatar', $child->avatar?->value ?? ChildAvatar::Anis->value);
@endphp

<x-layouts.app :title="$editing ? 'Ubah profil anak' : 'Tambah profil anak'">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <a href="{{ route('account.children') }}" class="pautan text-sm">← Kembali ke Anak-anak</a>
        <h1 class="mt-3 font-display text-3xl font-extrabold text-magenta">{{ $editing ? 'Ubah profil '.$child->nickname : 'Tambah profil anak' }}</h1>
        <p class="mt-1 text-ink/80">Kami hanya menyimpan nama panggilan dan kumpulan umur. Tiada foto, tarikh lahir atau sekolah.</p>

        <form method="POST" action="{{ $editing ? route('children.update', $child) : route('children.store') }}"
            class="mt-6 space-y-6 rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-field name="nickname" label="Nama panggilan" :value="$child->nickname" maxlength="30" required autocomplete="off"
                hint="Contoh: Adik, Along, Aisyah. Nama ini dipaparkan dalam Mod Anak." />

            <fieldset>
                <legend class="label">Kumpulan umur</legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ([AgeGroup::SixToNine, AgeGroup::TenToThirteen] as $group)
                        <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-full border-2 border-ink px-4 has-checked:bg-kuning">
                            <input type="radio" name="age_group" value="{{ $group->value }}" @checked($selectedAge === $group->value) class="size-5 text-biru" required>
                            {{ $group->getLabel() }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-sm text-ink/70">Rak buku dalam Mod Anak memaparkan buku yang sesuai dengan umur ini.</p>
                @error('age_group') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
            </fieldset>

            <fieldset>
                <legend class="label">Pilih avatar</legend>
                <div class="mt-2 grid grid-cols-4 gap-3">
                    @foreach (ChildAvatar::cases() as $avatar)
                        <label class="flex cursor-pointer flex-col items-center gap-1 rounded-2xl border-2 border-transparent p-2 has-checked:border-ink has-checked:bg-kuning/40">
                            <input type="radio" name="avatar" value="{{ $avatar->value }}" @checked($selectedAvatar === $avatar->value) class="sr-only" required>
                            <x-child-avatar :avatar="$avatar" />
                            <span class="text-sm font-semibold">{{ $avatar->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('avatar') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
            </fieldset>

            @if (app(App\Services\GamePlaying::class)->isOpen())
                {{-- Mod Anak games: the parent's daily limit (20 minutes for a new child). --}}
                <fieldset class="rounded-2xl bg-lavender p-4">
                    <legend class="sr-only">Permainan</legend>
                    <input type="hidden" name="game_settings" value="1">
                    <p class="font-display font-bold text-plum">Permainan</p>
                    <label for="daily_game_minutes" class="label mt-3">Had bermain sehari</label>
                    <select id="daily_game_minutes" name="daily_game_minutes" class="input">
                        <option value="">Tiada had</option>
                        @foreach (App\Services\GamePlaying::LIMIT_CHOICES as $minutes)
                            <option value="{{ $minutes }}" @selected((string) old('daily_game_minutes', $editing ? $child->daily_game_minutes : 20) === (string) $minutes)>{{ $minutes }} minit</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-sm text-ink/75">Permainan Islamik dalam Mod Anak: padan kad, huruf hijaiyah, kisah nabi dan akhlak.</p>
                    @error('daily_game_minutes') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                </fieldset>
            @endif

            @if (app(App\Services\VideoWatching::class)->isOpen())
                {{-- Phase 2: parent controls for the animated videos. --}}
                <fieldset class="rounded-2xl bg-lavender p-4">
                    <legend class="sr-only">Video animasi</legend>
                    <input type="hidden" name="video_settings" value="1">
                    <p class="font-display font-bold text-plum">Video animasi</p>
                    <label for="daily_video_minutes" class="label mt-3">Had menonton sehari</label>
                    <select id="daily_video_minutes" name="daily_video_minutes" class="input">
                        <option value="">Tiada had</option>
                        @foreach (App\Services\VideoWatching::LIMIT_CHOICES as $minutes)
                            <option value="{{ $minutes }}" @selected((string) old('daily_video_minutes', $child->daily_video_minutes) === (string) $minutes)>{{ $minutes }} minit</option>
                        @endforeach
                    </select>
                    @error('daily_video_minutes') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                    <label class="mt-4 flex min-h-11 cursor-pointer items-start gap-3">
                        <input type="hidden" name="read_before_watch" value="0">
                        <input type="checkbox" name="read_before_watch" value="1" class="mt-1 size-5 rounded text-biru" @checked(old('read_before_watch', $child->read_before_watch))>
                        <span><strong>Baca dulu, tonton kemudian</strong> (pilihan). Animasi sesuatu buku hanya terbuka selepas {{ $editing ? $child->nickname : 'anak' }} membaca buku itu.</span>
                    </label>
                </fieldset>
            @endif

            <button type="submit" class="btn-utama">{{ $editing ? 'Simpan' : 'Tambah anak' }}</button>
        </form>

        @if ($editing)
            <form method="POST" action="{{ route('children.destroy', $child) }}" class="mt-6 rounded-3xl border-2 border-magenta/40 bg-white p-6"
                data-confirm="Padam profil {{ $child->nickname }} dan semua rekod bacaannya? Tindakan ini tidak boleh dibatalkan."
                onsubmit="return confirm(this.dataset.confirm)">
                @csrf
                @method('DELETE')
                <h2 class="font-display text-lg font-bold text-plum">Padam profil</h2>
                <p class="mt-1 text-sm text-ink/80">Profil ini dan semua data bacaan, kuiz serta lencananya akan dipadam sepenuhnya.</p>
                <button type="submit" class="mt-3 inline-flex min-h-11 items-center rounded-full border-2 border-magenta px-5 font-bold text-magenta hover:bg-magenta hover:text-white">
                    Padam profil {{ $child->nickname }}
                </button>
            </form>
        @endif
    </div>
</x-layouts.app>
