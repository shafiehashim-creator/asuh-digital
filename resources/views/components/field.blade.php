@props(['name', 'label', 'type' => 'text', 'value' => null, 'bag' => 'default', 'hint' => null])

{{-- Labels and hints are BM sentences; lang/en.json translates them on the English site. --}}
@php($error = $errors->getBag($bag)->first($name))

<div>
    <label for="{{ $name }}" class="label">{{ __($label) }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($error) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class(['input', 'border-magenta!' => $error]) }}
    >
    @if ($hint && ! $error)
        <p class="mt-1 text-sm text-ink/70">{{ __($hint) }}</p>
    @endif
    @if ($error)
        <p id="{{ $name }}-error" class="mt-1 text-sm font-semibold text-magenta">{{ $error }}</p>
    @endif
</div>
