{{-- Kartu produk publik. Variabel: $product (kolom publik + relasi organization: id, slug, name). --}}
<article>
    <a href="{{ route('public.organization', $product->organization->slug) }}#produk" class="group block rounded-card">
        <div class="aspect-square overflow-hidden rounded-card border border-line bg-subtle">
            @if ($product->photo_path)
                <img src="{{ $product->photoUrl(thumbnail: true) }}" alt="{{ $product->name }}" width="400" height="400" loading="lazy" decoding="async" class="size-full object-cover">
            @else
                {{-- Pengganti foto: inisial nama produk, bukan gambar placeholder. --}}
                <div class="flex size-full items-center justify-center text-display font-semibold text-ink-muted" aria-hidden="true">{{ $product->initials() }}</div>
            @endif
        </div>
        <h3 class="mt-3 font-semibold text-ink transition-colors group-hover:text-brand">{{ $product->name }}</h3>
    </a>
    <p class="text-caption text-ink-soft">{{ $product->organization->name }}</p>
    @if ($product->capacity)
        <p class="text-caption text-ink-muted">{{ $product->capacity }}</p>
    @endif
</article>
