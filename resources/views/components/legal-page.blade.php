@props(['title', 'updated'])

<x-layouts.app :title="__($title)">
    <article class="mx-auto max-w-3xl px-4 py-10">
        @unless (app()->isProduction())
            {{-- CLAUDE.md: legal text must be reviewed before go-live. Hidden in production. --}}
            <div role="note" class="mb-6 rounded-xl border-2 border-oren bg-oren/15 px-4 py-3 text-sm font-semibold">
                {{ __('Draf: teks ini perlu disemak oleh pihak pengurusan atau undang-undang sebelum dilancarkan.') }}
            </div>
        @endunless

        <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">{{ __($title) }}</h1>
        <p class="mt-2 text-sm text-ink/70">{{ __('Kemas kini terakhir: :date', ['date' => $updated]) }}</p>

        <div class="legal mt-8 space-y-6 rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)] sm:p-8
            [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-plum
            [&_li]:ml-5 [&_li]:list-disc [&_li]:pl-1 [&_ul]:mt-2 [&_ul]:space-y-1 [&_p]:mt-2 [&_p]:text-ink/90">
            {{ $slot }}
        </div>
    </article>
</x-layouts.app>
