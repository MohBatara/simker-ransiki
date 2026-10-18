@props([
    // [key => ['label' => ..., 'href' => ..., 'count' => ?int, 'icon' => ?string]]; setiap tab punya URL sendiri.
    'tabs' => [],
    'active' => null,
    // Nama navigasi untuk pembaca layar (mis. "Laporan").
    'label' => null,
])

{{--
    Tab bertautan area Admin/OPD (menu bertab dan tab detail kelompok): garis bawah brand pada tab aktif, angka kecil
    bila relevan. HP: digeser horizontal, gradasi tipis di tepi kanan selama masih ada tab tersembunyi.
--}}
<div x-data="{ more: false, check() { const el = this.$refs.scroller; this.more = el.scrollLeft + el.clientWidth < el.scrollWidth - 1 } }" x-init="check()" x-on:resize.window="check()" {{ $attributes->class('relative') }}>
    <nav x-ref="scroller" x-on:scroll.passive="check()" class="scrollbar-none flex gap-6 overflow-x-auto border-b border-line" @if ($label) aria-label="{{ $label }}" @endif>
        @foreach ($tabs as $key => $tab)
            @php $current = $key === $active; @endphp
            <a
                href="{{ $tab['href'] }}"
                @if ($current) aria-current="page" x-data x-init="$el.scrollIntoView({ block: 'nearest', inline: 'nearest' })" @endif
                @class([
                    '-mb-px inline-flex min-h-11 items-center gap-2 whitespace-nowrap border-b-2 py-3 text-ui transition-colors',
                    'border-brand font-semibold text-ink' => $current,
                    'border-transparent text-ink-soft hover:border-line-strong hover:text-ink' => ! $current,
                ])
            >
                @if ($tab['icon'] ?? null)
                    <x-dynamic-component :component="'heroicon-o-'.$tab['icon']" @class(['size-5 shrink-0', 'text-brand' => $current, 'text-ink-muted' => ! $current]) aria-hidden="true" />
                @endif
                {{ $tab['label'] }}
                @if (($tab['count'] ?? null) !== null)
                    <span @class(['rounded-full px-2 text-caption tabular-nums', 'bg-brand-tint text-brand' => $current, 'bg-subtle text-ink-soft' => ! $current])>{{ \App\Support\Format::number($tab['count']) }}</span>
                @endif
            </a>
        @endforeach
    </nav>
    <span x-cloak x-show="more" class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-linear-to-l from-canvas to-transparent md:hidden" aria-hidden="true"></span>
</div>
