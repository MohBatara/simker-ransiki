@props([
    'title',
    // Satu kalimat petunjuk di bawah judul (kolom kiri di desktop).
    'description' => null,
])

@php
    $id = 'bagian-'.\Illuminate\Support\Str::slug($title);
@endphp

{{--
    Kartu section form seragam (putih, bingkai line, sudut 12px, padding 20px): judul 15px + satu kalimat petunjuk 13px di
    kolom kiri 280px, isian di kanan maksimal 720px mulai 1024px; label 13px medium, bantuan 12px (x-field). Di dalam
    x-admin.form-layout nav (form panjang) kolom kiri dipakai daftar section, jadi judul pindah ke atas isian. Bertumpuk di
    HP dan tablet. Slot "footer" untuk tombol simpan section (form yang tersimpan per bagian).
--}}
<section id="{{ $id }}" data-form-section data-title="{{ $title }}" {{ $attributes->class('min-w-0 scroll-mt-6 rounded-box border border-line bg-panel lg:group-data-[nav]/form:max-w-[762px]') }}>
    <div class="grid gap-x-8 gap-y-4 p-4 md:p-5 lg:grid-cols-[280px_minmax(0,720px)] lg:group-data-[nav]/form:grid-cols-[minmax(0,720px)]">
        <div>
            <h2 class="text-card-head font-semibold text-ink">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-caption text-ink-2">{{ $description }}</p>
            @endif
            @isset($aside)
                <div class="mt-3">{{ $aside }}</div>
            @endisset
        </div>
        <div class="min-w-0 space-y-5">
            {{ $slot }}
        </div>
    </div>
    @isset($footer)
        <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-line-soft px-4 py-3 md:px-5">{{ $footer }}</footer>
    @endisset
</section>
