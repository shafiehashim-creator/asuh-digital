<x-layouts.child :title="'Kuiz '.$book->title" :child="$child">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <a href="{{ route('child-mode.home') }}" class="pautan text-sm">← Rak buku</a>
        <h1 class="mt-2 font-display text-3xl font-extrabold text-magenta">Kuiz: {{ $book->title }}</h1>
        <p class="mt-1 font-semibold text-plum">Pilih jawapan yang betul. Jawab {{ App\Models\Quiz::PASS_PERCENT }}% betul untuk lulus!</p>

        @if ($errors->any())
            <p role="alert" class="mt-4 rounded-2xl border-2 border-magenta bg-white p-3 font-semibold text-magenta">Jawab semua soalan dahulu.</p>
        @endif

        <form method="POST" action="{{ route('child-mode.quiz.submit', $book) }}" class="mt-6 space-y-6">
            @csrf
            @foreach ($questions as $number => $question)
                <fieldset class="rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)]">
                    <legend class="sr-only">Soalan {{ $number + 1 }}</legend>
                    <p class="font-display text-xl font-bold"><span class="text-biru">{{ $number + 1 }}.</span> {{ $question['text'] }}</p>
                    <div class="mt-3 grid gap-2">
                        @foreach ($question['options'] as $option)
                            <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-2xl border-2 border-ink/20 px-4 py-2 text-lg font-semibold has-checked:border-ink has-checked:bg-kuning">
                                <input type="radio" name="answers[{{ $question['id'] }}]" value="{{ $option['index'] }}" class="size-5 text-biru"
                                    @checked((string) old('answers.'.$question['id']) === (string) $option['index']) required>
                                {{ $option['text'] }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <button type="submit" class="btn-utama w-full justify-center text-lg">Hantar jawapan</button>
        </form>
    </div>
</x-layouts.child>
