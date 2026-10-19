@props([
    // [key => ['label' => ..., 'href' => ..., 'count' => ?int, 'icon' => ?string]]; setiap tab punya URL sendiri.
    'tabs' => [],
    'active' => null,
    // Nama navigasi untuk pembaca layar (mis. "Laporan").
    'label' => null,
])

{{--
    Tab bertautan area aplikasi (menu bertab dan tab detail kelompok): teks 13px, garis bawah brand 2px pada tab aktif,
    hitungan dalam pil kecil netral. HP: digeser horizontal, gradasi tipis di tepi kanan selama masih ada tab tersembunyi.
--}}
<div x-data="{ more: false, check() { const el = this.$refs.scroller; this.more = el.scrollLeft + el.clientWidth < el.scrollWidth - 1 } }" x-init="check()" x-on:resize.window="check()" {{ $attributes->class('relative') }}>
    <nav x-ref="scroller" x-on:scroll.passive="check()" class="scrollbar-none flex gap-5 overflow-x-auto border-b border-line" @if ($label) aria-label="{{ $label }}" @endif>
        @foreach ($tabs as $key => $tab)
            @php $current = $key === $active; @endphp
            <a
                href="{{ $tab['href'] }}"
                @if ($current) aria-current="page" x-data x-init="$el.scrollIntoView({ block: 'nearest', inline: 'nearest' })" @endif
                @class([
                    '-mb-px inline-flex min-h-11 items-center gap-2 whitespace-nowrap border-b-2 py-2.5 text-caption transition-colors md:min-h-10',
                    'border-brand font-semibold text-brand' => $current,
                    'border-transparent font-medium text-ink-2 hover:border-line-strong hover:text-ink' => ! $current,
                ])
            >
                @if ($tab['icon'] ?? null)
                    <x-dynamic-component :component="'heroicon-o-'.$tab['icon']" @class(['size-4 shrink-0', 'text-brand' => $current, 'text-ink-faint' => ! $current]) aria-hidden="true" />
                @endif
                {{ $tab['label'] }}
                @if (($tab['count'] ?? null) !== null)
                    <span class="rounded-pill bg-idle-bg px-1.5 text-label font-medium tabular-nums text-idle">{{ \App\Support\Format::orEmpty(\App\Support\Format::number($tab['count'])) }}</span>
                @endif
            </a>
        @endforeach
    </nav>
    <span x-cloak x-show="more" class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-linear-to-l from-panel to-transparent md:hidden" aria-hidden="true"></span>
</div>
