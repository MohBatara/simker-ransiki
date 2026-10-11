{{-- Label pil (jenis, bidang usaha) di header detail kelompok; menyesuaikan warna di pita/banner forest. --}}
<span {{ $attributes->class([
    'inline-flex h-7 items-center rounded-full border px-3 text-caption',
    'border-line-strong bg-paper text-ink-soft',
    'md:in-[.app-band]:border-paper/50 md:in-[.app-band]:bg-transparent md:in-[.app-band]:text-paper',
    'in-[.app-band-photo]:border-paper/60 in-[.app-band-photo]:bg-ink/30 in-[.app-band-photo]:text-paper',
]) }}>{{ $slot }}</span>
