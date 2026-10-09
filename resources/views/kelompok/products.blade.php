<x-layouts.app title="Produk" description="Produk yang dihasilkan kelompok. Produk yang ditampilkan publik muncul di direktori.">
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
