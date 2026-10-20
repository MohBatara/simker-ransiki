@props([
    'title' => null,
    'description' => null,
    // Tanpa padding isi (tabel atau daftar selebar kartu).
    'flush' => false,
    // Ikon kecil brand di depan judul: KHUSUS detail kelompok (Bagian 7).
    'icon' => null,
    // Isi mengisi tinggi kartu (kartu sejajar dalam grid).
    'stretch' => false,
    // Tautan "Lihat semua" brand dengan panah di kanan kepala kartu.
    'more' => null,
    'moreLabel' => 'Lihat semua',
    // Judul dan aksi kepala sejajar tengah (kepala tanpa keterangan, mis. "Laporan saya" + "Semua laporan").
    'centerHeader' => false,
])

@php
    $hasHeader = $title || isset($actions) || $more;
@endphp

{{--
    Kartu area aplikasi: putih, bingkai line 1px, sudut 12px, padding 20px (HP 16px), tanpa bayangan. Kepala: judul 15px
    semibold (+ keterangan 13px), di kanan aksi kecil (pemilih h-8, tombol) atau tautan "Lihat semua" brand berpanah.
--}}
<section {{ $attributes->class(['min-w-0 rounded-box border border-line bg-panel', 'flex flex-col' => $stretch]) }}>
    @if ($hasHeader)
        <header class="flex flex-wrap {{ $centerHeader ? 'items-center' : 'items-start' }} justify-between gap-x-4 gap-y-2 px-4 pt-4 md:px-5 md:pt-5 {{ $flush ? 'pb-4' : '' }}">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="flex min-h-8 items-center gap-2 text-card-head font-semibold text-ink">
                        @if ($icon)
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-[18px] shrink-0 text-brand" aria-hidden="true" />
                        @endif
                        {{ $title }}
                    </h2>
                @endif
                @if ($description)
                    <p class="text-caption text-ink-3">{{ $description }}</p>
                @endif
            </div>
            @if (isset($actions) || $more)
                <div class="flex flex-wrap items-center gap-2 text-caption">
                    {{ $actions ?? '' }}
                    @if ($more)
                        <a href="{{ $more }}" class="inline-flex min-h-11 items-center gap-1 rounded-control font-medium text-brand hover:text-brand-hover hover:underline md:min-h-8">
                            {{ $moreLabel }} <x-heroicon-o-arrow-right class="size-3.5" aria-hidden="true" />
                        </a>
                    @endif
                </div>
            @endif
        </header>
    @endif

    <div @class([
        'flex min-w-0 flex-1 flex-col' => $stretch,
        'p-4 md:p-5' => ! $flush,
        'pt-3 md:pt-4' => ! $flush && $hasHeader,
    ])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-line-soft px-4 py-3 md:px-5">{{ $footer }}</footer>
    @endisset
</section>
