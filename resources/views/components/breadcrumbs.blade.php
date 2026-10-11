@props([
    // list of ['label' => 'Semua Kelompok', 'href' => ?string]; item terakhir = halaman ini (tanpa href).
    'items' => [],
])

{{-- Remah untuk halaman bertingkat; di HP digantikan tombol kembali di header atas. --}}
<nav aria-label="Remah" {{ $attributes->class('max-md:hidden') }}>
    <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-caption text-ink-soft md:in-[.app-band]:text-paper/85 in-[.app-band-photo]:text-paper/85">
        @foreach ($items as $item)
            <li class="flex items-center gap-1.5">
                @if (! $loop->first)
                    <x-heroicon-o-chevron-right class="size-3.5 text-ink-muted md:in-[.app-band]:text-paper/70 in-[.app-band-photo]:text-paper/70" aria-hidden="true" />
                @endif
                @if (($item['href'] ?? null) && ! $loop->last)
                    <a href="{{ $item['href'] }}" class="rounded-control hover:text-ink hover:underline md:in-[.app-band]:hover:text-paper in-[.app-band-photo]:hover:text-paper">{{ $item['label'] }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" class="text-ink md:in-[.app-band]:text-paper in-[.app-band-photo]:text-paper" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
