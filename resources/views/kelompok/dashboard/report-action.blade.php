{{--
    Satu tombol aksi untuk laporan suatu periode: Buat / Lanjutkan / Perbaiki / Lihat.
    Variabel: $period, $report (null bila belum dibuat), $canCreate, $variant (opsional), $class (opsional).
--}}
@use('App\Enums\ReportStatus')

@php
    $variant ??= 'primary';
    $class ??= '';
@endphp

@if ($report)
    @php
        [$label, $url] = match ($report->status) {
            ReportStatus::Draft => ['Lanjutkan', route('kelompok.reports.edit', $report)],
            ReportStatus::Revision => ['Perbaiki laporan', route('kelompok.reports.edit', $report)],
            default => ['Lihat laporan', route('kelompok.reports.show', $report)],
        };
    @endphp
    <x-button :variant="$variant" :href="$url" :class="$class">{{ $label }}</x-button>
@elseif ($canCreate)
    <form method="POST" action="{{ route('kelompok.reports.store') }}" class="inline-flex {{ $class }}">
        @csrf
        <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
        <x-button type="submit" :variant="$variant">Buat laporan</x-button>
    </form>
@endif
