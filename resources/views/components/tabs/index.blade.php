@props([
    'tabs' => [],
    'active' => null,
    // Isi dengan [key => url] agar tab menjadi tautan (mis. filter daftar di server).
    'links' => null,
    // [key => ikon Heroicons] untuk tab bertautan (mis. detail kelompok).
    'icons' => [],
])

@php
    $active ??= array_key_first($tabs);
    $navClasses = 'flex gap-6 overflow-x-auto border-b border-line';
    $tabClasses = '-mb-px inline-flex min-h-11 items-center gap-2 whitespace-nowrap border-b-2 py-3 text-ui transition-colors';
@endphp

@if ($links)
    <nav {{ $attributes->class($navClasses) }}>
        @foreach ($tabs as $key => $label)
            <a
                href="{{ $links[$key] }}"
                @if ($key === $active) aria-current="page" x-data x-init="$el.scrollIntoView({ block: 'nearest', inline: 'nearest' })" @endif
                @class([
                    $tabClasses,
                    'border-brand font-semibold text-ink' => $key === $active,
                    'border-transparent text-ink-soft hover:text-ink' => $key !== $active,
                ])
            >
                @if ($icons[$key] ?? null)
                    <x-dynamic-component :component="'heroicon-o-'.$icons[$key]" @class(['size-5 shrink-0', 'text-brand' => $key === $active, 'text-ink-muted' => $key !== $active]) aria-hidden="true" />
                @endif
                {{ $label }}
            </a>
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
