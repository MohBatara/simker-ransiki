@props([
    'title',
    // Satu kalimat petunjuk di bawah judul (kolom kiri di desktop).
    'description' => null,
])

{{--
    Kartu section form seragam: judul + satu kalimat petunjuk di kiri (4 kolom), isian di kanan (8 kolom) mulai 1024px;
    bertumpuk di HP dan tablet. Slot "footer" untuk tombol simpan section (form yang tersimpan per bagian).
--}}
<section {{ $attributes->class('min-w-0 rounded-tile border border-line bg-paper') }}>
    <div class="grid gap-x-8 gap-y-4 p-4 md:p-6 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <h2 class="text-body font-semibold text-ink">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-ui text-ink-soft">{{ $description }}</p>
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
        <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-line px-4 py-3 md:px-6">{{ $footer }}</footer>
    @endisset
</section>
