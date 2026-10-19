@props([
    // Keterangan singkat di kiri (mis. status laporan), opsional.
    'note' => null,
])

{{--
    Bar aksi yang menempel di bawah layar (semua ukuran) untuk tindakan utama halaman detail, mis. Verifikasi / Minta
    revisi di detail laporan. Di HP berada di atas navigasi bawah (--app-bottom dari layout app).
--}}
<div {{ $attributes->class('sticky bottom-[var(--app-bottom,0px)] z-10 -mx-4 mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line bg-panel px-4 pt-3 pb-[calc(0.75rem+var(--app-safe-bottom,0px))] md:bottom-0 md:-mx-7 md:rounded-t-box md:border-x md:px-7 lg:group-data-[nav]/form:mx-0 lg:group-data-[nav]/form:px-5 print:hidden') }}>
    @if ($note)
        <p class="min-w-0 flex-1 text-ui text-ink-soft max-md:w-full">{{ $note }}</p>
    @endif
    <div class="flex flex-wrap items-center gap-2 max-md:w-full max-md:*:flex-1 md:ml-auto">{{ $slot }}</div>
</div>
