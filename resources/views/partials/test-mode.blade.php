{{-- Penanda lingkungan non-produksi; tidak menangkap klik agar tidak menghalangi tombol di bawahnya. --}}
@unless (app()->isProduction())
    <div aria-hidden="true" class="pointer-events-none fixed bottom-[calc(var(--app-bottom,0px)+0.5rem)] left-2 z-50 rounded-control border border-wait/40 bg-wait-bg px-2 py-0.5 text-caption font-semibold text-wait md:bottom-3 md:left-auto md:right-3">Mode uji</div>
@endunless
