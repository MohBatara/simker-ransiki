@props([
    // ['webp' => [lebar => url], 'fallback' => url] dari Product::photoSources() atau App\Support\PublicImages.
    'image',
    'alt' => '',
    'sizes' => '100vw',
    'width' => null,
    'height' => null,
    // Kelas untuk elemen <picture> (pembungkus); atribut lain (class, dsb.) diteruskan ke <img>.
    'frame' => 'block',
    // Hanya gambar hero: dimuat segera dengan prioritas tinggi. Gambar lain lazy-load.
    'eager' => false,
])

<picture class="{{ $frame }}">
    @if (! empty($image['webp']))
        <source type="image/webp" sizes="{{ $sizes }}" srcset="{{ collect($image['webp'])->map(fn ($url, $width) => $url.' '.$width.'w')->join(', ') }}">
    @endif
    <img
        src="{{ $image['fallback'] }}"
        alt="{{ $alt }}"
        @if ($width) width="{{ $width }}" @endif
        @if ($height) height="{{ $height }}" @endif
        @if ($eager) fetchpriority="high" @else loading="lazy" @endif
        decoding="async"
        {{ $attributes }}
    >
</picture>
