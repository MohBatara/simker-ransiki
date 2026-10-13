{{--
    Baris angka ringkas (Profil Ransiki). Variabel: $stats (PublicDirectory::stats(), hanya data publik).
    HP grid 2×2; mulai 768px satu baris dipisah titik tengah, "Data per" di kanan.
--}}
@use('App\Support\Format')

<div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-3 border-y border-line py-5 {{ $class ?? '' }}">
    <dl class="grid w-full grid-cols-2 gap-x-6 gap-y-3 md:flex md:w-auto md:flex-wrap md:items-baseline md:gap-x-4">
        @foreach ([['organizations', 'Kelompok'], ['villages', 'Kampung'], ['featured', 'Produk unggulan'], ['sectors', 'Bidang usaha']] as [$key, $label])
            @unless ($loop->first)
                <span class="text-ink-muted max-md:hidden" aria-hidden="true">·</span>
            @endunless
            <div class="flex items-baseline gap-2">
                <dd class="order-first font-serif text-page-title font-semibold text-brand tabular-nums">{{ Format::number($stats[$key]) }}</dd>
                <dt class="text-ui text-ink-soft">{{ $label }}</dt>
            </div>
        @endforeach
    </dl>
    @if ($stats['updated_at'])
        <p class="text-caption text-ink-muted">Data per {{ Format::date($stats['updated_at']) }}</p>
    @endif
</div>
