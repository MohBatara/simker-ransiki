{{--
    Baris angka ringkas (Profil Ransiki). Variabel: $stats (PublicDirectory::stats(), hanya data publik).
    Angka serif brand yang menghitung naik (x-count). HP grid 2×2; mulai 768px satu baris 4 kolom bergaris pemisah.
--}}
@use('App\Support\Format')

<div class="border-t border-line pt-6 md:pt-8 {{ $class ?? '' }}" x-reveal="fade-up">
    <dl class="grid grid-cols-2 gap-x-6 gap-y-6 md:grid-cols-4 md:gap-0 md:divide-x md:divide-line">
        @foreach ([['organizations', 'Kelompok'], ['villages', 'Kampung'], ['featured', 'Produk unggulan'], ['sectors', 'Bidang usaha']] as [$key, $label])
            <div class="flex flex-col gap-1 md:px-6 md:first:pl-0">
                <dt class="text-ui text-ink-soft">{{ $label }}</dt>
                <dd class="order-first font-serif text-display font-semibold text-brand tabular-nums md:text-title-lg" x-count>{{ Format::number($stats[$key]) }}</dd>
            </div>
        @endforeach
    </dl>
    @if ($stats['updated_at'])
        <p class="mt-5 text-caption text-ink-muted">Data per {{ Format::date($stats['updated_at']) }}</p>
    @endif
</div>
