{{-- Rekap laporan satu periode (PDF). Variabel: $recap (App\Support\Exports\ReportRecap), $scope, $printedLine. --}}
@use('App\Support\Format')
@use('App\Support\Exports\ReportRecap')

@php
    $documentTitle = 'Rekap laporan bulanan';
    $subtitle = $scope;
    $periodLabel = Format::month($recap->period);
    $rows = $recap->statusRows();
    $economy = $recap->economy();
    $obligated = $rows->count();
    $verified = $economy['reports'];
    // NULL ditulis kosong, bukan 0.
    $number = fn ($value) => $value === null ? '' : Format::number($value);
    $quantity = fn ($value) => $value === null ? '' : Format::quantity($value);
    $date = fn ($value) => $value ? Format::date($value) : '';
    $production = $recap->production();
    $sales = $recap->sales();
@endphp

@extends('shared.exports.layout')

@section('content')
    <h2>Status pelaporan</h2>
    <table style="width: 60%">
        <tr><th>Status</th><th class="num">Kelompok</th></tr>
        @foreach ($recap->stateCounts() as $label => $total)
            <tr><td>{{ $label }}</td><td class="num">{{ $number($total) }}</td></tr>
        @endforeach
        <tr><th>Total kelompok wajib lapor</th><th class="num">{{ $number($obligated) }}</th></tr>
    </table>

    <h2>Ringkasan angka dari laporan terverifikasi</h2>
    <p>Laporan terverifikasi: {{ $verified }} dari {{ $obligated }} kelompok wajib lapor.</p>
    <table>
        <tr><th>Angka</th><th class="num">Jumlah</th><th>Keterangan</th></tr>
        @foreach ($economy['totals'] as $total)
            <tr>
                <td>{{ $total['label'] }}</td>
                <td class="num">{{ $number($total['sum']) }}</td>
                <td>{{ ReportRecap::filledNote($total['filled'], $verified) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Produksi per produk dan satuan</h2>
    @if ($production->isEmpty())
        <p class="empty">Belum ada data produksi dari laporan terverifikasi.</p>
    @else
        <table>
            <tr><th>Produk</th><th>Satuan</th><th class="num">Jumlah produksi</th><th>Keterangan</th></tr>
            @foreach ($production as $row)
                <tr>
                    <td>{{ $row['product'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td class="num">{{ $quantity($row['quantity']) }}</td>
                    <td>{{ ReportRecap::filledNote($row['filled'], $row['organizations']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Penjualan per produk dan satuan</h2>
    @if ($sales->isEmpty())
        <p class="empty">Belum ada data penjualan dari laporan terverifikasi.</p>
    @else
        <table>
            <tr><th>Produk</th><th>Satuan</th><th class="num">Jumlah terjual</th><th class="num">Pendapatan (Rp)</th><th>Keterangan</th></tr>
            @foreach ($sales as $row)
                <tr>
                    <td>{{ $row['product'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td class="num">{{ $quantity($row['quantity']) }}</td>
                    <td class="num">{{ $number($row['revenue']) }}</td>
                    <td>{{ ReportRecap::filledNote($row['filled'], $row['organizations']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Status laporan per kelompok</h2>
    @if ($rows->isEmpty())
        <p class="empty">Tidak ada kelompok yang wajib melapor pada periode ini.</p>
    @else
        <table>
            <tr><th>Kelompok</th><th>Kampung</th><th>OPD terkait</th><th>Status</th><th>Diajukan</th><th>Diverifikasi</th></tr>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['organization']->name }}<br><span class="muted">{{ $row['organization']->institutionType?->name }}</span></td>
                    <td>{{ $row['organization']->village?->name }}</td>
                    <td>{{ ReportRecap::opdNames($row['organization']) }}</td>
                    <td>{{ $row['state']->label() }}</td>
                    <td>{{ $date($row['report']?->submitted_at) }}@if ($row['report']?->wasSubmittedLate())<br><span class="muted">Diajukan terlambat</span>@endif</td>
                    <td>{{ $date($row['report']?->verified_at) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
