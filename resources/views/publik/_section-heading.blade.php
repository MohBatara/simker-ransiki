{{--
    Judul bagian di latar terang. Variabel: $title, $eyebrow (opsional, label kecil di atas judul),
    $link (opsional, ['href', 'label'] → tombol bergaris di kanan), $id (opsional, id judul).
--}}
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        @isset($eyebrow)
            <p class="mb-2 text-caption font-semibold uppercase tracking-wider text-brand">{{ $eyebrow }}</p>
        @endisset
        <h2 @isset($id) id="{{ $id }}" @endisset class="font-serif text-display-sm font-semibold text-ink">{{ $title }}</h2>
    </div>
    @isset($link)
        <x-public.button variant="outline-brand" :href="$link['href']" arrow class="max-sm:hidden">{{ $link['label'] }}</x-public.button>
    @endisset
</div>
