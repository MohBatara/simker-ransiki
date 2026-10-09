<x-layouts.public title="Contoh layout publik">
    <div class="mx-auto max-w-public px-4 py-10 md:px-6">
    <div class="max-w-2xl">
        <p class="text-caption text-ink-muted">Contoh layout publik</p>
        <h1 class="mt-1 text-display-sm font-semibold md:text-display">Direktori kelompok</h1>
        <p class="mt-3 text-body text-ink-soft">
            Daftar kelompok dan kelembagaan ekonomi di Distrik Ransiki. Data di halaman ini adalah contoh.
        </p>
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-2">
        @foreach (['Kelompok Contoh A' => 'KUB · Kampung Contoh 1', 'Kelompok Contoh B' => 'Kelompok Perikanan · Kampung Contoh 3', 'Kelompok Contoh C' => 'UMKM · Kampung Contoh 5', 'Kelompok Contoh D' => 'BUMDes · Kampung Contoh 2'] as $name => $meta)
            <x-card>
                <p class="text-card-title font-semibold">{{ $name }}</p>
                <p class="mt-1 text-caption text-ink-muted">{{ $meta }}</p>
                <p class="mt-3 text-body text-ink-soft">Contoh deskripsi singkat kegiatan usaha kelompok.</p>
            </x-card>
        @endforeach
    </div>

    <p class="mt-8">
        <a href="{{ route('dev.components.app') }}" class="rounded-control text-brand hover:text-brand-hover">Kembali ke halaman komponen</a>
    </p>
    </div>
</x-layouts.public>
