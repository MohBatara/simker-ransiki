@aware([
    'clickable' => false,
])

@props([
    // Tujuan klik baris. Link ada di sel "primary"; klik di bagian lain baris diteruskan lewat JS (openRowLink).
    'href' => null,
    // Tautan "Ubah" di menu titik tiga (opsional); slot "menu" untuk aksi lain yang sudah ada.
    'edit' => null,
    // Nama baris untuk label tombol menu (mis. nama kelompok).
    'for' => null,
])

<tr @if ($href) data-row-href @endif {{ $attributes->class([
    'relative transition-colors md:h-13',
    'cursor-pointer has-[[data-row-link]:focus-visible]:outline-2 has-[[data-row-link]:focus-visible]:-outline-offset-2 has-[[data-row-link]:focus-visible]:outline-brand' => $href,
    'max-md:grid max-md:grid-cols-[minmax(0,1fr)_auto] max-md:gap-x-3 max-md:rounded-box max-md:border max-md:border-line max-md:bg-panel max-md:px-4 max-md:py-3',
    'md:hover:bg-hover max-md:pr-10 max-md:active:bg-subtle' => $href,
]) }}>
    {{ $slot }}
    @if ($href)
        {{-- Tablet/desktop: titik tiga vertikal berisi Lihat (+ Ubah, aksi lain). HP: seluruh kartu bisa diketuk, chevron. --}}
        <td class="relative z-10 w-12 pr-2 text-right focus-within:z-20 max-md:absolute max-md:right-3 max-md:top-1/2 max-md:-translate-y-1/2 max-md:p-0">
            <x-heroicon-o-chevron-right class="ml-auto size-4 text-ink-faint md:hidden" aria-hidden="true" />
            <x-dropdown width="w-48" class="max-md:hidden">
                <x-slot:trigger>
                    <button type="button" x-bind:aria-expanded="open" aria-haspopup="menu" class="inline-flex size-8 items-center justify-center rounded-control text-ink-3 transition-colors hover:bg-subtle hover:text-ink" aria-label="Tindakan{{ $for ? ' untuk '.$for : '' }}">
                        <x-heroicon-o-ellipsis-vertical class="size-5" />
                    </button>
                </x-slot:trigger>
                <x-dropdown.item :href="$href" icon="eye">Lihat</x-dropdown.item>
                @if ($edit)
                    <x-dropdown.item :href="$edit" icon="pencil-square">Ubah</x-dropdown.item>
                @endif
                {{ $menu ?? '' }}
            </x-dropdown>
        </td>
    @elseif ($clickable)
        <td aria-hidden="true" class="w-12 max-md:hidden"></td>
    @endif
</tr>
