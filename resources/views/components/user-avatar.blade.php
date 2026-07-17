@props(['user', 'size' => 'w-8 h-8 text-xs'])

@if ($user->avatar_url)
    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
         {{ $attributes->merge(['class' => "$size rounded-full object-cover shrink-0"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$size inline-flex items-center justify-center rounded-full bg-brand-ink text-white font-semibold shrink-0"]) }}>
        {{ $user->initials() }}
    </span>
@endif
