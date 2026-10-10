@props([
    'action',
    // null = tanpa kotak cari
    'searchLabel' => null,
    'search' => null,
    // Jumlah filter pilihan yang aktif (untuk label "Filter (2)" di HP).
    'active' => 0,
    // Parameter yang dipertahankan sebagai input tersembunyi (mis. filter dari tautan dasbor).
    'keep' => [],
])

{{--
    Baris cari + filter: di layar lebar satu baris (select ringkas), di HP pilihan filter ada di panel
    yang dibuka tombol "Filter". Select di slot langsung menerapkan pilihan saat berubah.
--}}
<form method="GET" action="{{ $action }}" x-data="{ open: false }" role="search" {{ $attributes->class('flex flex-wrap items-center gap-2') }}>
    @foreach ($keep as $name => $value)
        @if (filled($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    @if ($searchLabel)
        <x-search-input :value="$search" :label="$searchLabel" class="flex-1 md:max-w-xs" />
        <x-button variant="ghost" type="submit">Cari</x-button>
    @endif

    @if ($slot->isNotEmpty())
        <x-button variant="secondary" icon="adjustments-horizontal" class="md:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="panel-filter">
            Filter{{ $active ? " ({$active})" : '' }}
        </x-button>
        <div id="panel-filter" x-bind:class="{ 'max-md:hidden': ! open }" class="grid w-full gap-2 max-md:hidden md:flex md:w-auto md:flex-wrap md:items-center md:*:w-44">
            {{ $slot }}
        </div>
    @endif

    <noscript><x-button variant="secondary" type="submit">Terapkan filter</x-button></noscript>
</form>
