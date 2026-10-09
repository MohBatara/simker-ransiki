{{-- Kartu produk publik. Variabel: $product (kolom publik + organization: id, slug, name, sectors: id, name). --}}
@php
    $url = route('public.organization', $product->organization->slug).'#produk';
    $sources = $product->photoSources();
    $sector = $product->organization->sectors->first();
@endphp

<article class="flex flex-col overflow-hidden rounded-card border border-line bg-paper">
    <a href="{{ $url }}" class="block aspect-[4/3] bg-subtle" tabindex="-1" aria-hidden="true">
        @if ($sources)
            <x-public.picture :image="$sources" :alt="$product->name" sizes="(min-width: 64rem) 16rem, 50vw" width="400" height="300" frame="block size-full" class="size-full object-cover" />
        @else
            {{-- Pengganti foto: inisial nama produk. --}}
            <span class="flex size-full items-center justify-center text-display font-semibold text-ink-muted">{{ $product->initials() }}</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col items-start gap-1.5 p-4">
        @if ($sector)
            <x-public.pill>{{ $sector->name }}</x-public.pill>
        @endif
        <h3 class="font-semibold"><a href="{{ $url }}" class="rounded-control text-ink hover:text-brand">{{ $product->name }}</a></h3>
        <p class="text-caption text-ink-soft">{{ $product->organization->name }}</p>
        @if ($product->capacity)
            <p class="text-caption text-ink-muted">{{ $product->capacity }}</p>
        @endif
    </div>
</article>
