{{-- Penanda lingkungan non-produksi di header area aplikasi (pil kecil di samping nama aplikasi). ink di atas wait-bg. --}}
@unless (app()->isProduction())
    <span @class(['inline-flex shrink-0 items-center rounded-full bg-wait-bg px-2 text-caption leading-5 font-semibold text-ink print:hidden', $class ?? null]) title="Mode uji: lingkungan uji, bukan data resmi">{{ ($short ?? false) ? 'Uji' : 'Mode uji' }}</span>
@endunless
