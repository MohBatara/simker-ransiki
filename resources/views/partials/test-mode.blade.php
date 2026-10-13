{{-- Penanda lingkungan non-produksi; tidak menangkap klik agar tidak menghalangi tombol di bawahnya.
     $top (website publik): pojok kiri atas tepat di bawah header, jauh dari footer dan tombol kembali ke atas. --}}
@unless (app()->isProduction())
    <div aria-hidden="true" @class([
        'pointer-events-none fixed z-50 rounded-control border border-wait/40 bg-wait-bg px-2 py-0.5 text-caption font-semibold text-wait print:hidden',
        'top-18 left-2 md:top-20 md:left-3' => $top ?? false,
        'bottom-[calc(var(--app-bottom,0px)+0.5rem)] left-2 md:bottom-3 md:left-auto md:right-3' => ! ($top ?? false),
    ])>Mode uji</div>
@endunless
