{{--
    Kartu produk Beranda (menaut ke detail produk). Variabel: $product (ProductSearch::COLUMNS + organization: id, slug, name, village, sectors: id, name, photo_path).
    Tanpa foto produk: foto bidang usaha dari Admin, atau petak inisial. Foto selalu 4:3 agar kartu sejajar; isi kartu
    berbaris tetap (bidang, judul 2 baris, kelompok, kampung) dan baris kapasitas di dasar kartu. Seluruh kartu dapat
    diklik lewat satu tautan di judul (lapisan after:inset-0); cincin fokus mengelilingi kartu.
--}}
@use('App\Support\PublicImages')

@php
    $url = route('public.product', $product->slug);
    $sector = $product->organization->sectors->first();
    $sources = $product->photoSources() ?? ($sector ? PublicImages::sectorPhoto($sector) : null);
@endphp

<article class="group relative flex h-full flex-col overflow-hidden rounded-public border border-line bg-paper shadow-card has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-brand">
    <div class="aspect-[4/3] overflow-hidden bg-subtle">
        @if ($sources)
            <x-public.picture :image="$sources" alt="" sizes="(min-width: 64rem) 16rem, (min-width: 48rem) 50vw, 80vw" width="400" height="300" frame="block size-full" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04] motion-reduce:transition-none motion-reduce:group-hover:scale-100" />
        @else
            {{-- Ukuran 20px sengaja di luar skala token, atas permintaan pengguna. --}}
            <span class="flex size-full items-center justify-center text-[1.25rem] font-semibold text-ink-muted" aria-hidden="true">{{ $product->initials() }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-4">
        <p class="text-caption font-semibold tracking-wider text-clay uppercase">{{ $sector?->name ?? "\u{00A0}" }}</p>
        <h3 class="mt-1 line-clamp-2 min-h-[3.25rem] font-serif text-card-title font-semibold"><a href="{{ $url }}" class="text-ink after:absolute after:inset-0 hover:text-brand focus-visible:outline-none">{{ $product->name }}</a></h3>
        <p class="mt-2 truncate text-ui text-ink-soft" title="{{ $product->organization->name }}">{{ $product->organization->name }}</p>
        <p class="mt-1 flex min-w-0 items-center gap-1.5 text-caption text-ink-muted">
            <x-heroicon-o-map-pin class="size-4 shrink-0" aria-hidden="true" /><span class="truncate">{{ $product->organization->village->name }}</span>
        </p>
        <div class="mt-auto pt-4">
            <p class="flex items-baseline justify-between gap-3 border-t border-line pt-3">
                <span class="text-caption text-ink-muted">Kapasitas</span>
                <span @class(['truncate text-ui', 'font-semibold text-ink' => $product->capacity, 'text-ink-muted' => ! $product->capacity])>{{ $product->capacity ?? 'Belum diisi' }}</span>
            </p>
        </div>
    </div>
</article>
