<x-layouts.public title="Produk unggulan" description="Produk hasil kelompok usaha dan kelembagaan ekonomi Distrik Ransiki.">
    <x-public.band title="Produk unggulan" :crumbs="['Produk unggulan' => null]" lead="Hasil usaha kelompok di Distrik Ransiki. Pilih produk untuk melihat profil dan kontak kelompoknya." />

    <div class="mx-auto max-w-public px-4 py-section md:px-6">
    <form method="GET" action="{{ route('public.products') }}" class="max-w-xs">
        <x-select name="bidang" :options="$sectors->pluck('name', 'id')->all()" :selected="$sector" placeholder="Semua bidang usaha" aria-label="Bidang usaha" x-data x-on:change="$el.form.requestSubmit()" />
    </form>

    @if ($featured->isEmpty() && $others->isEmpty())
        <div class="mt-section-head">
            <x-empty-state title="Belum ada produk yang dipublikasikan" description="Produk tampil di sini setelah kelompok mempublikasikannya." />
        </div>
    @endif

    @if ($featured->isNotEmpty())
        <section class="mt-section-head">
            @include('publik._section-heading', ['title' => 'Unggulan'])
            <div class="mt-section-head grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($featured as $product)
                    @include('publik._product-card')
                @endforeach
            </div>
        </section>
    @endif

    @if ($others->isNotEmpty())
        <section class="mt-section">
            @include('publik._section-heading', ['title' => $featured->isEmpty() ? 'Produk' : 'Produk lainnya'])
            <div class="mt-section-head grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($others as $product)
                    @include('publik._product-card')
                @endforeach
            </div>
            @if ($others->hasPages())
                <div class="mt-8">{{ $others->links() }}</div>
            @endif
        </section>
    @endif
    </div>
</x-layouts.public>
