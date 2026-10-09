{{-- Tiga angka ringkas. Variabel: $stats dari App\Support\PublicDirectory::stats(). --}}
@use('App\Support\Format')

<dl class="grid grid-cols-3 gap-4 {{ $class ?? '' }}">
    {{-- [kunci, label HP (satu baris), label layar lebar] --}}
    @foreach ([['organizations', 'kelompok', 'kelompok terdaftar'], ['products', 'produk', 'produk'], ['sectors', 'bidang usaha', 'bidang usaha']] as [$key, $short, $label])
        {{-- Angka di atas label; justify-end menjaga angka tetap sejajar walau label turun dua baris. --}}
        <div class="flex flex-col-reverse justify-end {{ $itemClass ?? '' }}">
            <dt class="whitespace-nowrap text-caption text-ink-soft">
                <span class="md:hidden">{{ $short }}</span><span class="max-md:hidden">{{ $label }}</span>
            </dt>
            <dd class="text-display-sm font-semibold tabular-nums text-ink md:text-display">{{ Format::number($stats[$key]) }}</dd>
        </div>
    @endforeach
</dl>
