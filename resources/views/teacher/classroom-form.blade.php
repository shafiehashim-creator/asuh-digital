@php
    use App\Enums\AgeGroup;

    $editing = $classroom->exists;
    $selectedAge = old('age_group', $classroom->age_group?->value);
@endphp

<x-layouts.app :title="$editing ? 'Ubah kelas' : 'Cipta kelas'">
    <div class="mx-auto max-w-xl px-4 py-10">
        <a href="{{ $editing ? route('teacher.classrooms.show', $classroom) : route('teacher.home') }}" class="pautan text-sm">← Kembali</a>
        <h1 class="mt-3 font-display text-3xl font-extrabold text-magenta">{{ $editing ? 'Ubah '.$classroom->name : 'Cipta kelas' }}</h1>

        <form method="POST" action="{{ $editing ? route('teacher.classrooms.update', $classroom) : route('teacher.classrooms.store') }}"
            class="mt-6 space-y-6 rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-field name="name" label="Nama kelas" :value="$classroom->name" maxlength="60" required hint="Contoh: 2 Bestari, Kelas Iqra' Petang" />

            <fieldset>
                <legend class="label">Kumpulan umur murid</legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ([AgeGroup::SixToNine, AgeGroup::TenToThirteen] as $group)
                        <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-full border-2 border-ink px-4 has-checked:bg-kuning">
                            <input type="radio" name="age_group" value="{{ $group->value }}" @checked($selectedAge === $group->value) class="size-5 text-biru" required>
                            {{ $group->getLabel() }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-sm text-ink/70">Murid hanya melihat buku untuk umur ini.</p>
                @error('age_group') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
            </fieldset>

            <button type="submit" class="btn-utama">{{ $editing ? 'Simpan' : 'Cipta kelas' }}</button>
        </form>

        @if ($editing)
            <form method="POST" action="{{ route('teacher.classrooms.destroy', $classroom) }}" class="mt-6 rounded-3xl border-2 border-magenta/40 bg-white p-6"
                data-confirm="Padam {{ $classroom->name }} dan semua murid serta rekod bacaan mereka? Tindakan ini tidak boleh dibatalkan."
                onsubmit="return confirm(this.dataset.confirm)">
                @csrf
                @method('DELETE')
                <h2 class="font-display text-lg font-bold text-plum">Padam kelas</h2>
                <p class="mt-1 text-sm text-ink/80">Semua murid dalam kelas ini, rekod bacaan, kuiz dan lencana mereka akan dipadam.</p>
                <button type="submit" class="mt-3 inline-flex min-h-11 items-center rounded-full border-2 border-magenta px-5 font-bold text-magenta hover:bg-magenta hover:text-white">Padam kelas</button>
            </form>
        @endif
    </div>
</x-layouts.app>
