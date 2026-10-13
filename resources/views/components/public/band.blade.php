@props([
    'title',
    'lead' => null,
    // Opsional: sumber <picture> untuk latar foto + overlay (hanya Profil Ransiki); default forest polos.
    'image' => null,
    // Remah: [label => url|null]; "Beranda" selalu di depan, item terakhir tanpa tautan.
    'crumbs' => [],
])

{{-- Pita judul halaman publik berlatar forest. Slot "top" untuk label, slot utama untuk isi tambahan. --}}
<section class="on-dark relative isolate overflow-hidden bg-forest text-paper">
    @if ($image)
        <x-public.picture :image="$image" alt="" eager width="1600" height="686" frame="absolute inset-0 -z-20 block size-full" class="size-full object-cover" />
        <div class="overlay-forest absolute inset-0 -z-10" aria-hidden="true"></div>
    @endif
    <div class="mx-auto max-w-public px-4 py-section md:px-6">
        @if ($crumbs)
            <nav aria-label="Remah roti" class="mb-4 text-caption text-forest-soft">
                <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                    @foreach (['Beranda' => route('home')] + $crumbs as $label => $url)
                        <li class="flex items-center gap-1.5">
                            @unless ($loop->first)<x-heroicon-o-chevron-right class="size-3.5" aria-hidden="true" />@endunless
                            @if ($url && ! $loop->last)
                                <a href="{{ $url }}" class="rounded-control text-paper hover:underline">{{ $label }}</a>
                            @else
                                <span @if ($loop->last) aria-current="page" @endif>{{ $label }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        {{ $top ?? '' }}
        <h1 class="max-w-3xl font-serif text-display-sm font-semibold md:text-display">{{ $title }}</h1>
        @if ($lead)
            <p class="mt-3 max-w-[42rem] text-body text-forest-soft">{{ $lead }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
