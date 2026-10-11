{{--
    Detail satu laporan bulanan (PDF siap cetak): bagian A–H, status, dan riwayat/catatan verifikasi.
    Variabel: $report (loadDetail), $attachments, $printedLine.
--}}
@use('App\Support\Format')
@use('App\Enums\IssueKind')

@php
    $documentTitle = 'Laporan bulanan';
    $subtitle = $report->organization->name;
    $periodLabel = Format::month($report->period);
    // NULL ditulis kosong, bukan 0.
    $number = fn ($value) => $value === null ? '' : Format::number($value);
    $rupiah = fn ($value) => $value === null ? '' : Format::rupiah($value);
    $quantity = fn ($value, $unit) => $value === null ? '' : trim(Format::quantity($value).' '.$unit);
    $date = fn ($value) => $value ? Format::date($value) : '';
    $issues = [
        'E. Permasalahan' => [$report->issues->where('kind', IssueKind::Problem)->sortBy('id'), 'Tidak ada masalah yang dicatat.'],
        'F. Kebutuhan dukungan' => [$report->issues->where('kind', IssueKind::Support)->sortBy('id'), 'Tidak ada kebutuhan yang dicatat.'],
    ];
@endphp

@extends('shared.exports.layout')

@section('content')
    <table class="pairs">
        <tr><td>Status</td><td>{{ $report->status->label() }}@if ($report->isOverdue()) · Terlambat @endif</td></tr>
        <tr><td>Tenggat</td><td>{{ Format::date($report->deadline()) }}</td></tr>
        <tr><td>Diajukan</td><td>{{ $date($report->submitted_at) }}@if ($report->wasSubmittedLate()) (diajukan terlambat)@endif</td></tr>
        <tr><td>Diverifikasi</td><td>{{ $date($report->verified_at) }}@if ($report->verified_at && $report->verifier) oleh {{ $report->verifier->name }}@endif</td></tr>
        <tr><td>Kampung</td><td>{{ $report->organization->village?->name }}</td></tr>
    </table>

    <h2>A. Aktivitas</h2>
    @if ($report->no_activity)
        <p>Tidak ada aktivitas bulan ini.</p>
    @endif
    <table class="pairs">
        <tr><td>Ringkasan aktivitas</td><td class="text">{{ $report->activity_summary }}</td></tr>
        <tr><td>Jumlah kegiatan</td><td>{{ $number($report->activity_count) }}</td></tr>
        <tr><td>Capaian</td><td class="text">{{ $report->achievements }}</td></tr>
    </table>

    <h2>B. Produksi &amp; penjualan</h2>
    @if ($report->items->isEmpty())
        <p class="empty">Tidak ada produk yang dilaporkan.</p>
    @else
        <table>
            <tr><th>Produk</th><th class="num">Produksi</th><th class="num">Terjual</th><th class="num">Pendapatan</th></tr>
            @foreach ($report->items->sortBy('id') as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td class="num">{{ $quantity($item->production_qty, $item->production_unit) }}</td>
                    <td class="num">{{ $quantity($item->sales_qty, $item->sales_unit) }}</td>
                    <td class="num">{{ $rupiah($item->revenue) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>C. Sumber daya manusia</h2>
    <table class="pairs">
        <tr><td>Anggota aktif</td><td>{{ $number($report->members_active) }}</td></tr>
        <tr><td>Tenaga kerja</td><td>{{ $number($report->workforce_count) }}</td></tr>
        <tr><td>Perubahan anggota</td><td>{{ $report->members_change > 0 ? '+' : '' }}{{ $number($report->members_change) }}</td></tr>
    </table>

    <h2>D. Keuangan</h2>
    <table class="pairs">
        <tr><td>Pendapatan penjualan</td><td>{{ $rupiah($report->income_sales) }}</td></tr>
        <tr><td>Pendapatan lain</td><td>{{ $rupiah($report->income_other) }}</td></tr>
        <tr><td>Biaya</td><td>{{ $rupiah($report->costs) }}</td></tr>
        <tr><td>Laba (dihitung otomatis)</td><td>{{ $rupiah($report->profit) }}</td></tr>
    </table>
    @if ($report->profit === null)
        <p class="muted">Laba tidak dihitung karena ada angka yang kosong.</p>
    @endif

    @foreach ($issues as $title => [$rows, $empty])
        <h2>{{ $title }}</h2>
        @if ($rows->isEmpty())
            <p class="empty">{{ $empty }}</p>
        @else
            <table>
                <tr><th style="width: 30%">Kategori</th><th>Uraian</th></tr>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row->category ? $row->categoryLabel() : 'Tanpa kategori' }}</td>
                        <td class="text">{{ $row->description }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endforeach

    <h2>G. Dokumentasi</h2>
    @if ($attachments->isEmpty())
        <p class="empty">Tidak ada dokumentasi.</p>
    @else
        <table>
            <tr><th>Nama file</th><th class="num">Ukuran</th><th>Diunggah</th></tr>
            @foreach ($attachments as $attachment)
                <tr>
                    <td>{{ $attachment->original_name }}</td>
                    <td class="num">{{ Format::fileSize($attachment->size) }}</td>
                    <td>{{ Format::date($attachment->created_at) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>H. Riwayat dan catatan verifikasi</h2>
    <table>
        <tr><th style="width: 30%">Waktu</th><th>Tindakan</th></tr>
        <tr><td>{{ Format::dateTime($report->created_at) }}</td><td>Laporan dibuat</td></tr>
        @foreach ($report->reviews as $review)
            <tr>
                <td>{{ Format::dateTime($review->created_at) }}</td>
                <td>
                    {{ $review->action->label() }} oleh {{ $review->user?->name ?? 'akun yang sudah dihapus' }}
                    @if (filled($review->note))
                        <div class="note">{{ $review->note }}</div>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endsection
