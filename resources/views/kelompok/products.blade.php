<x-layouts.app title="Produk" description="Produk yang dihasilkan kelompok. Produk yang ditampilkan publik muncul di direktori.">
    @can('create', [App\Models\Product::class, $organization])
        <x-slot:actions>
            <x-button icon="plus" x-data x-on:click="$dispatch('product-create')">Tambah produk</x-button>
        </x-slot:actions>
    @endcan

    @include('shared.organizations.products', [
        'canManage' => auth()->user()->can('create', [App\Models\Product::class, $organization]),
        'canFeature' => false,
        'canPhoto' => auth()->user()->can('update', $organization),
        'urls' => [
            'store' => route('kelompok.products.store'),
            'update' => route('kelompok.products.update', '__ID__'),
            'destroy' => route('kelompok.products.destroy', '__ID__'),
        ],
    ])
</x-layouts.app>
