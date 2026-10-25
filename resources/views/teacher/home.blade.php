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
            @else
                <p class="mt-2 text-ink/80">Tiada lesen aktif. Murid tidak boleh membaca sehingga lesen diperbaharui. Hubungi pasukan ASUH Digital.</p>
            @endif
        </section>
    </div>
</x-layouts.app>
