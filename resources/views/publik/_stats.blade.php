{{-- Tiga angka ringkas. Variabel: $stats dari App\Support\PublicDirectory::stats(). --}}
@use('App\Support\Format')

<dl class="grid grid-cols-3 gap-4 {{ $class ?? '' }}">
    @foreach ([['organizations', 'kelompok terdaftar'], ['products', 'produk'], ['sectors', 'bidang usaha']] as [$key, $label])
        {{-- Angka di atas label; justify-end menjaga angka tetap sejajar walau label turun dua baris. --}}
        <div class="flex flex-col-reverse justify-end {{ $itemClass ?? '' }}">
            <dt class="text-caption text-ink-soft">{{ $label }}</dt>
            <dd class="text-display-sm font-semibold tabular-nums text-ink md:text-display">{{ Format::number($stats[$key]) }}</dd>
        </div>
    @endforeach
</dl>
