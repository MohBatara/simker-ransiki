@props([
    'title' => null,
    'description' => null,
    // Tanpa padding isi (tabel atau daftar selebar kartu).
    'flush' => false,
    // Ikon kecil brand di depan judul: KHUSUS detail kelompok (Bagian 7).
    'icon' => null,
    // Isi mengisi tinggi kartu (kartu sejajar dalam grid).
    'stretch' => false,
])

@php
    $hasHeader = $title || isset($actions);
@endphp

{{--
    Kartu/section area aplikasi: putih, garis line, sudut 16px, padding 16px HP / 24px mulai tablet. Kepala: judul 16px
    semibold + subjudul 13px, aksi kecil di kanan, garis pemisah tipis di bawah kepala.
--}}
<section {{ $attributes->class(['min-w-0 rounded-tile border border-line bg-paper', 'flex flex-col' => $stretch]) }}>
    @if ($hasHeader)
        <header class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 border-b border-line px-4 py-4 md:px-6">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="flex items-center gap-2 text-body font-semibold text-ink">
                        @if ($icon)
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5 shrink-0 text-brand" aria-hidden="true" />
                        @endif
                        {{ $title }}
                    </h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-caption text-ink-muted">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2 text-ui">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class([
        'flex min-w-0 flex-1 flex-col' => $stretch,
        'p-4 md:p-6' => ! $flush,
    ])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-line px-4 py-3 md:px-6">{{ $footer }}</footer>
    @endisset
</section>
