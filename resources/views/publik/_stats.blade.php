{{--
    Tiga angka ringkas. Variabel: $stats (PublicDirectory::stats()), $tone ('sun' pita kuning, 'dark' latar forest),
    $class (opsional, tambahan untuk <dl>).
--}}
@use('App\Support\Format')

@php
    $tone ??= 'sun';
@endphp

<dl class="grid grid-cols-3 gap-4 {{ $class ?? '' }}">
    {{-- [kunci, label HP (satu baris), label layar lebar] --}}
    @foreach ([['organizations', 'kelompok', 'kelompok terdaftar'], ['products', 'produk', 'produk'], ['sectors', 'bidang usaha', 'bidang usaha']] as [$key, $short, $label])
        {{-- Angka di atas label; justify-end menjaga angka sejajar walau label turun dua baris. --}}
        <div class="flex flex-col-reverse justify-end">
            <dt @class(['whitespace-nowrap text-caption', 'text-ink' => $tone === 'sun', 'text-forest-soft' => $tone === 'dark'])>
                <span class="md:hidden">{{ $short }}</span><span class="max-md:hidden">{{ $label }}</span>
            </dt>
            <dd @class(['text-display-sm font-semibold tabular-nums md:text-display', 'text-ink' => $tone === 'sun', 'text-paper' => $tone === 'dark'])>{{ Format::number($stats[$key]) }}</dd>
        </div>
    @endforeach
</dl>
