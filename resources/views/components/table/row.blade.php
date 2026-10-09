@props([
    // Tujuan klik baris. Link ditempatkan di sel "primary" dan diperluas menutupi seluruh baris.
    'href' => null,
])

<tr {{ $attributes->class([
    'relative transition-colors',
    'max-md:grid max-md:grid-cols-[minmax(0,1fr)_auto] max-md:gap-x-3 max-md:rounded-card max-md:border max-md:border-line max-md:bg-paper max-md:px-4 max-md:py-3',
    'md:hover:bg-subtle max-md:pr-10 max-md:active:bg-subtle' => $href,
]) }}>
    {{ $slot }}
    @if ($href)
        <td aria-hidden="true" class="w-10 pr-4 text-ink-muted max-md:absolute max-md:right-3 max-md:top-1/2 max-md:-translate-y-1/2 max-md:p-0">
            <x-heroicon-o-chevron-right class="ml-auto size-4" />
        </td>
    @endif
</tr>
