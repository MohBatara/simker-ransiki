@aware([
    'clickable' => false,
])

@props([
    // Tujuan klik baris. Link ada di sel "primary"; klik di bagian lain baris diteruskan lewat JS (openRowLink).
    'href' => null,
])

<tr @if ($href) data-row-href @endif {{ $attributes->class([
    'relative transition-colors md:h-14',
    'cursor-pointer has-[[data-row-link]:focus-visible]:outline-2 has-[[data-row-link]:focus-visible]:-outline-offset-2 has-[[data-row-link]:focus-visible]:outline-brand' => $href,
    'max-md:grid max-md:grid-cols-[minmax(0,1fr)_auto] max-md:gap-x-3 max-md:rounded-tile max-md:border max-md:border-line max-md:bg-paper max-md:px-4 max-md:py-3',
    'md:hover:bg-subtle/60 max-md:pr-10 max-md:active:bg-subtle' => $href,
]) }}>
    {{ $slot }}
    @if ($href)
        <td aria-hidden="true" class="w-10 pr-4 text-ink-muted max-md:absolute max-md:right-3 max-md:top-1/2 max-md:-translate-y-1/2 max-md:p-0">
            <x-heroicon-o-chevron-right class="ml-auto size-4" />
        </td>
    @elseif ($clickable)
        <td aria-hidden="true" class="w-10 max-md:hidden"></td>
    @endif
</tr>
