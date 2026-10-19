@props([
    'title',
    // Satu kalimat petunjuk di bawah judul (kolom kiri di desktop).
    'description' => null,
])

{{--
    Kartu section form seragam (putih, bingkai line, sudut 12px, padding 20px): judul 15px + satu kalimat petunjuk 13px
    di kiri (4 kolom), isian di kanan (8 kolom) mulai 1024px; label 13px medium, bantuan 12px (x-field);
    bertumpuk di HP dan tablet. Slot "footer" untuk tombol simpan section (form yang tersimpan per bagian).
--}}
<section {{ $attributes->class('min-w-0 rounded-box border border-line bg-panel') }}>
    <div class="grid gap-x-8 gap-y-4 p-4 md:p-5 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <h2 class="text-card-head font-semibold text-ink">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-caption text-ink-2">{{ $description }}</p>
            @endif
            @isset($aside)
                <div class="mt-3">{{ $aside }}</div>
            @endisset
        </div>
        <div class="min-w-0 space-y-5 lg:col-span-8">
            {{ $slot }}
        </div>
    </div>
    @isset($footer)
        <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-line-soft px-4 py-3 md:px-5">{{ $footer }}</footer>
    @endisset
</section>
