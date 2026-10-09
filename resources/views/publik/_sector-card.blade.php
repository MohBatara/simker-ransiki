{{-- Kartu bidang usaha berfoto. Variabel: $sector dari PublicDirectory::sectors(). Tanpa foto: kartu forest polos. --}}
@use('App\Support\Format')
@use('App\Support\PublicImages')

@php
    $image = PublicImages::sector($sector);
@endphp

{{-- Tanpa foto, kartu dibuat lebih pendek agar tidak menjadi blok gelap besar yang kosong. --}}
<a href="{{ route('public.directory', ['bidang' => $sector->id]) }}" @class(['group relative block overflow-hidden rounded-card bg-forest', 'aspect-[4/3]' => $image, 'aspect-[2/1]' => ! $image])>
    @if ($image)
        <x-public.picture :image="$image" alt="" sizes="(min-width: 64rem) 22rem, (min-width: 40rem) 50vw, 100vw" width="480" height="360" frame="absolute inset-0 block size-full" class="size-full object-cover" />
    @endif
    <span class="on-dark absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 bg-forest px-3 py-2.5 md:px-4 md:py-3">
        <span>
            <span class="block font-semibold text-paper">{{ $sector->name }}</span>
            <span class="text-caption text-sun tabular-nums">{{ Format::number($sector->organizations_count) }} kelompok</span>
        </span>
        <x-heroicon-o-arrow-right class="size-5 shrink-0 text-paper transition-transform group-hover:translate-x-0.5 max-sm:hidden" />
    </span>
</a>
