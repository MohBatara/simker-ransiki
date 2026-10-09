@props([
    'tabs' => [],
    'active' => null,
    // Isi dengan [key => url] agar tab menjadi tautan (mis. filter daftar di server).
    'links' => null,
])

@php
    $active ??= array_key_first($tabs);
    $navClasses = 'flex gap-6 overflow-x-auto border-b border-line';
    $tabClasses = '-mb-px whitespace-nowrap border-b-2 py-3 text-ui transition-colors';
@endphp

@if ($links)
    <nav {{ $attributes->class($navClasses) }}>
        @foreach ($tabs as $key => $label)
            <a
                href="{{ $links[$key] }}"
                @if ($key === $active) aria-current="page" @endif
                @class([
                    $tabClasses,
                    'border-brand font-semibold text-ink' => $key === $active,
                    'border-transparent text-ink-soft hover:text-ink' => $key !== $active,
                ])
            >{{ $label }}</a>
        @endforeach
    </nav>
@else
    <div x-data="{ tab: @js($active) }" {{ $attributes }}>
        <div role="tablist" class="{{ $navClasses }}">
            @foreach ($tabs as $key => $label)
                <button
                    type="button"
                    role="tab"
                    x-on:click="tab = @js($key)"
                    x-bind:aria-selected="tab === @js($key)"
                    x-bind:class="tab === @js($key) ? 'border-brand font-semibold text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                    class="{{ $tabClasses }}"
                >{{ $label }}</button>
            @endforeach
        </div>
        <div class="pt-4">{{ $slot }}</div>
    </div>
@endif
