{{--
    Judul bagian di latar terang. Variabel: $title, $eyebrow (opsional, label kecil di atas judul),
    $link (opsional, ['href', 'label'] → tombol bergaris di kanan), $id (opsional, id judul).
--}}
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        @isset($eyebrow)
            {{-- Teks sun tidak terbaca di latar terang (1,8:1): label brand, aksen sun hanya garis. --}}
            <p class="mb-2 flex items-center gap-2 text-caption font-semibold uppercase tracking-wider text-brand">
                <span class="h-0.5 w-6 bg-sun" aria-hidden="true"></span>{{ $eyebrow }}
            </p>
        @endisset
        <h2 @isset($id) id="{{ $id }}" @endisset class="font-serif text-display-sm font-semibold text-ink">{{ $title }}</h2>
    </div>
    @isset($link)
        <x-public.button variant="outline-brand" :href="$link['href']" arrow class="max-sm:hidden">{{ $link['label'] }}</x-public.button>
    @endisset
</div>
