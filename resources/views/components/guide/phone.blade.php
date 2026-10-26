{{-- A simple drawn phone for the parents' guide: the screen is the slot. --}}
<figure {{ $attributes->class('mx-auto w-56 shrink-0') }} aria-hidden="true">
    <div class="rounded-[2rem] border-4 border-ink bg-ink p-1.5 shadow-[0_6px_0_rgb(35_31_32/0.25)]">
        <div class="relative h-96 overflow-hidden rounded-[1.6rem] bg-latar">
            <div class="flex h-7 items-center justify-center bg-plum"><span class="h-1.5 w-12 rounded-full bg-white/30"></span></div>
            <div class="space-y-2 p-3 text-[11px] leading-tight">{{ $slot }}</div>
        </div>
    </div>
</figure>
