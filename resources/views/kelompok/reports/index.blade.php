@use('App\Support\Format')
@use('App\Enums\OrganizationStatus')

<x-layouts.app
    title="Laporan Bulanan"
    :description="'Satu laporan untuk setiap bulan. Tenggat: tanggal '.config('simker.reports.deadline_day').' bulan berikutnya, pukul 23.59 WIT.'"
>
    @if ($organization->status === OrganizationStatus::Closed)
        <x-alert class="mb-5">Kelompok ini sudah tutup, sehingga tidak bisa membuat laporan baru. Draf atau laporan yang diminta revisi tetap bisa diselesaikan.</x-alert>
    @endif

    @error('period')
        <x-alert tone="fix" class="mb-5">{{ $message }}</x-alert>
    @enderror

    @if ($rows->isEmpty())
        <x-empty-state
            title="Belum ada bulan yang wajib dilaporkan"
            :description="'Kewajiban lapor dimulai '.(Format::month($organization->reporting_starts_on)).'. Laporan pertama bisa dibuat mulai bulan itu.'"
        />
    @else
        <x-table clickable>
            <x-slot:head>
                <x-table.heading>Periode</x-table.heading>
                <x-table.heading>Status</x-table.heading>
                <x-table.heading>Tenggat</x-table.heading>
                <x-table.heading>Diajukan</x-table.heading>
                <x-table.heading><span class="sr-only">Aksi</span></x-table.heading>
            </x-slot:head>

            @foreach ($rows as $row)
                @php
                    $report = $row['report'];
                    $href = $report ? ($report->status->isEditable() ? route('kelompok.reports.edit', $report) : route('kelompok.reports.show', $report)) : null;
                    $overdue = $report ? $report->isOverdue() : $row['pastDeadline'];
                @endphp
                <x-table.row :href="$href">
                    <x-table.cell primary>{{ Format::month($row['period']) }}</x-table.cell>
                    <x-table.cell badge>
                        <span class="inline-flex flex-wrap items-center justify-end gap-1.5 md:justify-start">
                            @if ($report)
                                <x-badge :tone="$report->status->badgeColor()">{{ $report->status->label() }}</x-badge>
                            @else
                                <span class="text-ink-muted">Belum dibuat</span>
                            @endif
                            @if ($overdue)
                                <x-badge tone="fix">Lewat tenggat</x-badge>
                            @endif
                        </span>
                    </x-table.cell>
                    <x-table.cell label="Tenggat">{{ Format::date($row['deadline']) }}</x-table.cell>
                    <x-table.cell label="Diajukan">
                        {{ Format::date($report?->submitted_at) }}
                        @if ($report?->wasSubmittedLate())
                            <span class="block text-caption text-ink-soft">Diajukan terlambat</span>
                        @endif
                    </x-table.cell>
                    <x-table.cell @class(['md:text-right', 'max-md:hidden' => $report || ! $canCreate]) :actions="! $report && $canCreate">
                        @if (! $report && $canCreate)
                            <form method="POST" action="{{ route('kelompok.reports.store') }}" class="relative z-10">
                                @csrf
                                <input type="hidden" name="period" value="{{ $row['period']->format('Y-m') }}">
                                <x-button type="submit" variant="secondary" class="max-md:w-full" processing="Membuat…">Buat laporan</x-button>
                            </form>
                        @endif
                    </x-table.cell>
                </x-table.row>
            @endforeach

            @if ($rows->hasPages())
                <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
            @endif
        </x-table>
    @endif
</x-layouts.app>
