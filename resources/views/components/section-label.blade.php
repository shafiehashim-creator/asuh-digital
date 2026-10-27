{{-- The small pill above a sales page heading. --}}
<p {{ $attributes->class(['inline-flex items-center gap-2 rounded-full border-2 border-ink/15 bg-white px-3 py-1 text-xs font-extrabold tracking-wider text-oren uppercase']) }}>
    <span class="size-2 rounded-full bg-oren" aria-hidden="true"></span>{{ $slot }}
</p>
