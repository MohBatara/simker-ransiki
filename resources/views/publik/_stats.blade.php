{{--
    Baris angka ringkas (Profil Ransiki). Variabel: $stats (PublicDirectory::stats(), hanya data publik).
    Empat ubin besar (sudut 20px, garis tipis): angka serif brand 48px yang menghitung naik (x-count), label di bawah.
    HP grid 2×2 (angka 36px); mulai 768px satu baris.
--}}
@use('App\Support\Format')

<div class="{{ $class ?? '' }}" x-reveal="fade-up">
    <dl class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
        @foreach ([['organizations', 'Kelompok'], ['villages', 'Kampung'], ['featured', 'Produk unggulan'], ['sectors', 'Bidang usaha']] as [$key, $label])
            <div class="flex min-w-0 flex-col gap-2 rounded-[20px] border border-line bg-paper p-5 md:p-6">
                <dt class="text-ui text-ink-soft">{{ $label }}</dt>
                <dd class="order-first font-serif text-hero-sm font-semibold tracking-[-0.01em] text-brand tabular-nums md:text-hero" x-count>{{ Format::number($stats[$key]) }}</dd>
            </div>
        @endforeach
    </dl>
    @if ($stats['updated_at'])
        <p class="mt-4 text-caption text-ink-muted">Data per {{ Format::date($stats['updated_at']) }}</p>
    @endif
</div>
