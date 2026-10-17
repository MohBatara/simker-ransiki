{{--
    Kartu katalog produk. Variabel: $product (ProductSearch::COLUMNS + CARD_RELATIONS), opsional $search (sorotan kata
    kunci), $sectorId (bidang yang sedang disaring, dipakai sebagai label), $large (deretan Pilihan distrik: foto 4:5).
    Foto 1:1 bersudut 16px; tanpa foto: panel cream dengan ikon bidang warna clay. Seluruh kartu menaut ke detail produk
    lewat satu tautan di judul (lapisan after:inset-0); "Lihat detail" hanya penanda visual saat hover/fokus.
--}}
@use('App\Support\PublicIcons')

@php
    $large ??= false;
    $sectors = $product->organization->sectors->sortBy('name');
    $sector = (isset($sectorId) ? $sectors->firstWhere('id', $sectorId) : null) ?? $sectors->first();
    $sources = $product->photoSources();
    $name = isset($search) ? $search->highlight($product->name) : e($product->name);
@endphp

<article class="group relative flex h-full flex-col has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-4 has-[a:focus-visible]:outline-brand has-[a:focus-visible]:rounded-tile">
    <div @class(['relative overflow-hidden rounded-tile bg-cream', 'aspect-[4/5]' => $large, 'aspect-square' => ! $large])>
        @if ($sources)
            <x-public.picture :image="$sources" alt="" :sizes="$large ? '(min-width: 64rem) 22rem, (min-width: 48rem) 40vw, 75vw' : '(min-width: 64rem) 16rem, (min-width: 48rem) 33vw, 50vw'" width="400" :height="$large ? 500 : 400" frame="block size-full" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04] motion-reduce:transition-none motion-reduce:group-hover:scale-100" />
        @else
            <span class="flex size-full items-center justify-center text-clay" aria-hidden="true">
                <x-public.icon :name="PublicIcons::sector($sector?->name)" @class(['stroke-1', 'size-16' => $large, 'size-12 md:size-14' => ! $large]) />
            </span>
        @endif
        @if ($product->is_featured)
            <span class="absolute top-3 left-3 inline-flex h-7 items-center gap-1 rounded-full bg-sun text-ink px-2.5 text-caption font-semibold">
                <x-heroicon-o-star class="size-3.5 shrink-0" aria-hidden="true" /><span class="max-sm:sr-only">Pilihan distrik</span><span class="sm:hidden" aria-hidden="true">Pilihan</span>
            </span>
        @endif
        <span class="absolute bottom-3 left-3 inline-flex h-8 translate-y-2 items-center gap-1.5 rounded-full bg-paper px-3 text-caption font-semibold text-ink opacity-0 shadow-soft transition-[opacity,translate] duration-150 group-hover:translate-y-0 group-hover:opacity-100 group-has-[a:focus-visible]:translate-y-0 group-has-[a:focus-visible]:opacity-100 motion-reduce:transition-none max-md:hidden" aria-hidden="true">
            Lihat detail<x-heroicon-o-arrow-right class="size-3.5" />
        </span>
    </div>

    <div class="flex flex-1 flex-col pt-3 md:pt-4">
        <p class="truncate text-caption font-semibold tracking-wider text-clay uppercase">{{ $sector?->name ?? "\u{00A0}" }}</p>
        <h3 class="mt-1 line-clamp-2 min-h-[3.25rem] font-serif text-card-title font-semibold">
            <a href="{{ route('public.product', $product->slug) }}" title="{{ $product->name }}" class="text-ink after:absolute after:inset-0 focus-visible:outline-none group-hover:text-brand">{!! $name !!}</a>
        </h3>
        <p class="mt-1 truncate text-ui text-ink-soft" title="{{ $product->organization->name }}">{{ $product->organization->name }}</p>
        <p class="mt-1 flex min-w-0 items-center gap-1.5 text-caption text-ink-muted">
            <x-heroicon-o-map-pin class="size-4 shrink-0" aria-hidden="true" /><span class="truncate">{{ $product->organization->village->name }}</span>
        </p>
        <div class="mt-auto pt-3">
            <p class="flex flex-wrap items-baseline justify-between gap-x-3 border-t border-line pt-3">
                <span class="text-caption text-ink-muted">Kapasitas</span>
                <span @class(['ml-auto min-w-0 text-right text-ui', 'font-semibold text-ink' => $product->capacity, 'text-ink-muted' => ! $product->capacity]) @if ($product->capacity) title="{{ $product->capacity }}" @endif>{{ $product->capacity ?? 'Belum diisi' }}</span>
            </p>
        </div>
    </div>
</article>
