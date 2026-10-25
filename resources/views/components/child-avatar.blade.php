@props(['avatar', 'size' => 'md'])

@php
    $avatar = $avatar instanceof App\Enums\ChildAvatar ? $avatar : App\Enums\ChildAvatar::from($avatar);
    $box = $size === 'lg' ? 'size-24 text-5xl' : 'size-14 text-3xl';
@endphp

<span {{ $attributes->class(["inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-ink bg-lavender {$box}"]) }} aria-hidden="true">
    @if ($avatar->image())
        <img src="{{ Vite::asset($avatar->image()) }}" alt="" class="h-full w-full object-cover object-top">
    @else
        {{ $avatar->emoji() }}
    @endif
</span>
