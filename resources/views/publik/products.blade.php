<x-layouts.public title="Produk unggulan" description="Produk hasil kelompok usaha dan kelembagaan ekonomi Distrik Ransiki.">
    <h1 class="text-display-sm font-semibold text-ink md:text-display">Produk unggulan</h1>
    <p class="mt-2 max-w-[42rem] text-body text-ink-soft">Hasil usaha kelompok di Distrik Ransiki. Pilih produk untuk melihat profil dan kontak kelompoknya.</p>

    <form method="GET" action="{{ route('public.products') }}" class="mt-6 max-w-xs">
        <x-select name="bidang" :options="$sectors->pluck('name', 'id')->all()" :selected="$sector" placeholder="Semua bidang usaha" aria-label="Bidang usaha" x-data x-on:change="$el.form.requestSubmit()" />
    </form>

    @if ($featured->isEmpty() && $others->isEmpty())
        <div class="mt-8">
            <x-empty-state title="Belum ada produk yang dipublikasikan" description="Produk tampil di sini setelah kelompok mempublikasikannya." />
        </div>
    @endif

    @if ($featured->isNotEmpty())
        <section class="mt-10">
            @include('publik._section-heading', ['title' => 'Unggulan'])
            <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-4">
                @foreach ($featured as $product)
                    @include('publik._product-card')
                @endforeach
            </div>
        </section>
    @endif

    @if ($others->isNotEmpty())
        <section class="mt-12">
            @include('publik._section-heading', ['title' => $featured->isEmpty() ? 'Produk' : 'Produk lainnya'])
            <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-4">
                @foreach ($others as $product)
                    @include('publik._product-card')
                @endforeach
            </div>
            <div class="mt-8">{{ $others->links() }}</div>
        </section>
    @endif
</x-layouts.public>
