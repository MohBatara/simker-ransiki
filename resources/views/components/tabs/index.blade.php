@props([
    'tabs' => [],
    'active' => null,
    // Isi dengan [key => url] agar tab menjadi tautan (mis. filter daftar di server).
    'links' => null,
    // [key => ikon Heroicons] untuk tab bertautan (mis. detail kelompok).
    'icons' => [],
    // [key => angka] kecil di tab bertautan (mis. jumlah menunggu); null = tanpa angka, 0 tetap tampil.
    'counts' => [],
    // Nama navigasi tab bertautan untuk pembaca layar (mis. "Laporan").
    'label' => null,
])

@php
    $active ??= array_key_first($tabs);
    $navClasses = 'flex gap-6 overflow-x-auto border-b border-line';
    // HP: gradasi tipis di tepi kanan selama masih ada tab yang tersembunyi (penanda bisa digeser).
    $scroller = '{ more: false, check() { const el = this.$refs.scroller; this.more = el.scrollLeft + el.clientWidth < el.scrollWidth - 1 } }';
    $fade = '<span x-cloak x-show="more" class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-linear-to-l from-canvas to-transparent md:hidden" aria-hidden="true"></span>';
    $tabClasses = '-mb-px inline-flex min-h-11 items-center gap-2 whitespace-nowrap border-b-2 py-3 text-ui transition-colors';
@endphp

@if ($links)
    <div x-data="{!! $scroller !!}" x-init="check()" x-on:resize.window="check()" {{ $attributes->class('relative') }}>
    <nav x-ref="scroller" x-on:scroll.passive="check()" class="{{ $navClasses }}" @if ($label) aria-label="{{ $label }}" @endif>
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
                @if (($counts[$key] ?? null) !== null)
                    <span @class(['rounded-full px-2 text-caption tabular-nums', 'bg-brand-tint text-brand' => $key === $active, 'bg-subtle text-ink-soft' => $key !== $active])>{{ \App\Support\Format::number($counts[$key]) }}</span>
                @endif
            </a>
        @endforeach
    </nav>
    {!! $fade !!}
    </div>
@else
    <div x-data="{ tab: @js($active), ...{!! $scroller !!} }" x-init="check()" x-on:resize.window="check()" {{ $attributes }}>
        <div class="relative">
        <div role="tablist" x-ref="scroller" x-on:scroll.passive="check()" class="{{ $navClasses }}">
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
        {!! $fade !!}
        </div>
        <div class="pt-4">{{ $slot }}</div>
    </div>
@endif
