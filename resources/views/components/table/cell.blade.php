@props([
    'label' => null,
    'numeric' => false,
    'optional' => false,
    'primary' => false,
    'actions' => false,
])

@php
    $showLabel = $label && ! $primary && ! $actions;
@endphp

<td
    @if ($showLabel) data-label="{{ $label }}" @endif
    {{ $attributes->class([
        'px-4 py-3 align-middle',
        'max-md:flex max-md:items-center max-md:justify-between max-md:gap-4 max-md:px-0 max-md:py-1 max-md:text-right',
        'max-md:before:shrink-0 max-md:before:text-left max-md:before:text-ink-soft max-md:before:content-[attr(data-label)]' => $showLabel,
        'tabular-nums md:text-right' => $numeric,
        'max-md:pb-2 max-md:text-left max-md:font-semibold' => $primary,
        'whitespace-nowrap md:text-right max-md:mt-2 max-md:justify-end max-md:border-t max-md:border-line max-md:pt-3' => $actions,
        'md:max-xl:hidden' => $optional,
    ]) }}
>{{ $slot }}</td>
