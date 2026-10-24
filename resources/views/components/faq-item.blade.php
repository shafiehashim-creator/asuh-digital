@props(['question'])

<details class="group rounded-2xl border-2 border-ink bg-white px-5 open:shadow-[0_3px_0_var(--color-ink)]">
    <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-3 font-bold [&::-webkit-details-marker]:hidden">
        {{ $question }}
        <span aria-hidden="true" class="font-display text-2xl text-biru transition group-open:rotate-45">+</span>
    </summary>
    <div class="pb-4 text-ink/80">{{ $slot }}</div>
</details>
