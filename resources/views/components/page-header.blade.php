@props([
    'title',
    'description' => null,
    'eyebrow' => null,
    'back' => null,
    // Remah untuk halaman bertingkat (desktop); menggantikan tautan "Kembali". Lihat x-breadcrumbs.
    'breadcrumbs' => null,
    // Dipakai layout app: di HP judul dan tombol kembali sudah ada di header atas.
    'condensed' => false,
    // Pita forest polos mulai tablet (di HP tetap putih). Bagian 7: hanya area aplikasi, bukan halaman form.
    'band' => false,
    // Banner foto (['webp' => [...], 'fallback' => url], lihat App\Support\BannerImage): dasbor dan detail kelompok.
    'image' => null,
    // Banner foto di HP sekitar 25% lebih pendek (detail kelompok); desktop tidak berubah.
    'compact' => false,
    // Banner foto lebih pendek di tablet/desktop (180px, dasbor) agar ringkasan dan grafik cepat terlihat.
    'short' => false,
])

{{--
    Header halaman. Isi slot (aksi, meta, badge) menyesuaikan warna lewat varian in-[.app-band] (pita, mulai
    tablet) dan in-[.app-band-photo] (banner foto, semua ukuran). Lihat x-button, x-link, x-breadcrumbs.
--}}
@php
    $mode = $image ? 'photo' : ($band ? 'band' : 'plain');
    $soft = match ($mode) {
        'photo' => 'text-paper/85',
        'band' => 'text-ink-soft md:text-paper/85',
        default => 'text-ink-soft',
    };
    $strong = match ($mode) {
        'photo' => 'text-paper',
        'band' => 'text-ink md:text-paper',
        default => 'text-ink',
    };
    $hideOnPhone = $condensed && $mode !== 'photo';
@endphp

{{--
    Mode foto: mulai tablet seluruh header berada di atas foto (.app-band). Di HP foto hanya di blok judul
    (.app-band-photo: judul + keterangan); meta (tag) dan aksi turun ke baris di bawah banner berwarna biasa.
--}}
<div {{ $attributes->class([
    'flex flex-col gap-4 md:flex-row md:justify-between',
    'relative isolate' => $mode !== 'photo',
    'md:items-end' => $mode !== 'photo',
    'max-md:hidden' => $hideOnPhone && ! $description && ! isset($actions) && ! isset($badge) && ! isset($meta),
    'app-band md:overflow-hidden md:rounded-card md:bg-forest md:px-6 md:py-6 xl:px-8' => $mode === 'band',
    'app-band md:relative md:isolate md:items-start md:overflow-hidden md:rounded-card md:bg-forest md:px-6 xl:px-8' => $mode === 'photo',
    'md:min-h-52 md:py-8' => $mode === 'photo' && ! $short,
    'md:min-h-45 md:py-6' => $mode === 'photo' && $short,
]) }}>
    <div class="min-w-0 md:max-w-[60%]">
        <div @class([
            'app-band-photo max-md:relative max-md:isolate max-md:flex max-md:flex-col max-md:justify-end max-md:overflow-hidden max-md:rounded-card max-md:bg-forest max-md:px-4' => $mode === 'photo',
            'max-md:min-h-32 max-md:py-5' => $mode === 'photo' && ! $compact,
            'max-md:min-h-24 max-md:py-4' => $mode === 'photo' && $compact,
        ])>
            @if ($mode === 'photo')
                <x-public.picture :image="$image" alt="" eager sizes="(min-width: 80rem) 1100px, 100vw" width="1600" height="400" frame="absolute inset-0 -z-20 block size-full" class="size-full object-cover" />
                <div class="overlay-forest absolute inset-0 -z-10" aria-hidden="true"></div>
            @endif

            @if ($breadcrumbs)
                <x-breadcrumbs :items="$breadcrumbs" class="mb-2" />
            @elseif ($back)
                <a href="{{ $back }}" @class(['mb-2 inline-flex items-center gap-1 rounded-control text-caption hover:underline', $soft, 'max-md:hidden' => $condensed])>
                    <x-heroicon-o-arrow-left class="size-4" />
                    Kembali
                </a>
            @endif
            @if ($eyebrow)
                <p @class(['text-caption', $soft, 'max-md:hidden' => $hideOnPhone])>{{ $eyebrow }}</p>
            @endif
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h1 @class(['text-page-title font-semibold', $strong, 'max-md:sr-only' => $hideOnPhone])>{{ $title }}</h1>
                @isset($badge)
                    {{ $badge }}
                @endisset
            </div>
            @if ($description)
                <p @class(['mt-1 text-ui', $soft])>{{ $description }}</p>
            @endif
        </div>
        @isset($meta)
            <div class="mt-3">{{ $meta }}</div>
        @endisset
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 md:shrink-0">{{ $actions }}</div>
    @endisset
</div>
