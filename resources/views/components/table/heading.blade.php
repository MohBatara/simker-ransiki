@props([
    'numeric' => false,
    'optional' => false,
])

<th scope="col" {{ $attributes->class([
    'h-10 whitespace-nowrap px-4 text-caption font-semibold text-ink-soft',
    'text-right' => $numeric,
    'text-left' => ! $numeric,
    'md:max-xl:hidden' => $optional,
]) }}>{{ $slot }}</th>
