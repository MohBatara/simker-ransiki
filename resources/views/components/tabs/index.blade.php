@props([
    // [key => label]; tab dalam halaman (panel Alpine, mis. panel ringkas kelompok). Tab bertautan: x-admin.tabs.
    'tabs' => [],
    'active' => null,
])

@php
    $active ??= array_key_first($tabs);
@endphp

{{-- Gaya sama dengan x-admin.tabs: garis bawah brand pada tab aktif; HP digeser dengan gradasi tipis di tepi kanan. --}}
<div x-data="{ tab: @js($active), more: false, check() { const el = this.$refs.scroller; this.more = el.scrollLeft + el.clientWidth < el.scrollWidth - 1 } }" x-init="check()" x-on:resize.window="check()" {{ $attributes }}>
    <div class="relative">
        <div role="tablist" x-ref="scroller" x-on:scroll.passive="check()" class="scrollbar-none flex gap-6 overflow-x-auto border-b border-line">
            @foreach ($tabs as $key => $label)
                <button
                    type="button"
                    role="tab"
                    x-on:click="tab = @js($key)"
                    x-bind:aria-selected="tab === @js($key)"
                    x-bind:class="tab === @js($key) ? 'border-brand font-semibold text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                    class="-mb-px inline-flex min-h-11 items-center gap-2 whitespace-nowrap border-b-2 py-3 text-ui transition-colors"
                >{{ $label }}</button>
            @endforeach
        </div>
        <span x-cloak x-show="more" class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-linear-to-l from-canvas to-transparent md:hidden" aria-hidden="true"></span>
    </div>
    <div class="pt-4">{{ $slot }}</div>
</div>
