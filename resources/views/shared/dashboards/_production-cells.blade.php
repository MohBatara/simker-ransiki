{{--
    Sel bersama tabel "Produksi per produk" (dasbor Admin/OPD dan tab Ekonomi), baris dari Statistics::production().
    $cell = 'organizations': 1 kelompok → namanya (teks kecil, satu baris, nama lengkap di tooltip); lebih → "3 kelompok" +
    daftar nama di tooltip. $cell = 'change': persen setara (naik hijau, turun merah, panah), "Baru", atau
    "belum bisa dibandingkan". Variabel: $row, $cell.
--}}
@use('App\Support\Format')

@if ($cell === 'organizations')
    @if ($row['organizations'] === 1 && $row['organization_names'])
        <span class="block max-w-40 truncate text-label text-ink-2" title="{{ $row['organization_names'][0] }}">{{ $row['organization_names'][0] }}</span>
    @else
        <span class="cursor-help whitespace-nowrap tabular-nums" title="{{ implode(', ', $row['organization_names']) }}">{{ Format::number($row['organizations']) }} kelompok</span>
    @endif
@else
    @if ($row['change']['status'] === 'compared')
        @php
            $change = $row['change'];
            $value = $change['percent'];
            $up = $value > 0;
            $flat = (int) round($value) === 0;
            $title = 'Dibanding '.$change['previous'].', dari '.$change['matched'].' kelompok yang sama';
        @endphp
        <span title="{{ $title }}" @class([
            'inline-flex cursor-help items-center gap-0.5 whitespace-nowrap text-label font-medium tabular-nums',
            'text-ok' => ! $flat && $up,
            'text-fix' => ! $flat && ! $up,
            'text-ink-3' => $flat,
        ]) data-production-change="{{ $flat ? 'flat' : ($up ? 'up' : 'down') }}">
            @unless ($flat)
                <x-dynamic-component :component="$up ? 'heroicon-o-arrow-up' : 'heroicon-o-arrow-down'" class="size-3" aria-hidden="true" />
            @endunless
            {{ Format::number(abs($value)) }}%
            <span class="sr-only">{{ $flat ? 'tidak berubah' : ($up ? 'naik' : 'turun') }}. {{ $title }}.</span>
        </span>
    @elseif ($row['change']['status'] === 'new')
        <span class="cursor-help text-label font-medium text-ink-2" title="Tidak ada kelompok yang melaporkan produk dan satuan ini pada {{ $row['change']['previous'] }}" data-production-change="new">Baru</span>
    @else
        <span class="cursor-help text-label text-ink-3" title="Tidak ada kelompok yang mengisi jumlah produk ini pada kedua periode" data-production-change="incomparable">belum bisa dibandingkan</span>
    @endif
@endif
