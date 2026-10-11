{{--
    Kartu produk publik. Variabel: $product (kolom publik + organization: id, slug, name, village, sectors: id, name, photo_path).
    Tanpa foto produk: foto bidang usaha dari Admin, atau area inisial 16:9 agar tidak mendominasi kartu.
--}}
@use('App\Support\PublicIcons')
@use('App\Support\PublicImages')

@php
    $url = route('public.organization', $product->organization->slug).'#produk';
    $sector = $product->organization->sectors->first();
    $sources = $product->photoSources() ?? ($sector ? PublicImages::sectorPhoto($sector) : null);
@endphp

<article class="flex flex-col overflow-hidden rounded-public border border-line bg-paper shadow-card">
    <div class="relative">
        @if ($sources)
            <a href="{{ $url }}" class="block aspect-[4/3] overflow-hidden bg-subtle" tabindex="-1" aria-hidden="true">
                <x-public.picture :image="$sources" :alt="$product->name" sizes="(min-width: 64rem) 16rem, 50vw" width="400" height="300" frame="block size-full" class="size-full object-cover" />
            </a>
        @else
            {{-- Ukuran 20px sengaja di luar skala token, atas permintaan pengguna. --}}
            <a href="{{ $url }}" class="flex aspect-[16/9] items-center justify-center bg-subtle text-[1.25rem] font-semibold text-ink-muted" tabindex="-1" aria-hidden="true">{{ $product->initials() }}</a>
        @endif
        <x-public.icon-badge :icon="PublicIcons::sector($sector?->name)" class="absolute -bottom-6 left-4" />
    </div>
    <div class="flex flex-1 flex-col items-start gap-1 px-4 pt-8 pb-4">
        <h3 class="font-serif text-card-title font-semibold"><a href="{{ $url }}" class="rounded-control text-ink hover:text-brand">{{ $product->name }}</a></h3>
        <p class="text-ui text-ink-soft">{{ $product->organization->name }}</p>
        <p class="flex items-center gap-1.5 text-caption text-ink-muted">
            <x-heroicon-o-map-pin class="size-4 shrink-0" aria-hidden="true" />{{ $product->organization->village->name }}
        </p>
        @if ($product->capacity)
            <p class="text-caption text-ink-muted">{{ $product->capacity }}</p>
        @endif
    </div>
</article>
