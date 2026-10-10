@use('App\Support\Format')

@if ($reports->isEmpty())
    <x-empty-state title="Belum ada laporan" description="Laporan bulanan dibuat oleh akun kelompok mulai {{ Format::month($organization->reporting_starts_on) }}." />
@else
    <x-table clickable>
        <x-slot:head>
            <x-table.heading>Periode</x-table.heading>
            <x-table.heading>Status</x-table.heading>
            <x-table.heading>Diajukan</x-table.heading>
            <x-table.heading numeric>Laba</x-table.heading>
        </x-slot:head>

        @foreach ($reports as $report)
            {{-- Draf yang belum pernah diajukan tidak bisa dibuka peninjau. --}}
            <x-table.row :href="$report->submitted_at ? route($area.'.reports.show', [$report, 'dari' => 'kelompok']) : null">
                <x-table.cell primary>{{ Format::month($report->period) }}</x-table.cell>
                <x-table.cell badge><x-badge :tone="$report->status->badgeColor()">{{ $report->status->reviewerLabel() }}</x-badge></x-table.cell>
                <x-table.cell label="Diajukan">{{ Format::date($report->submitted_at) }}</x-table.cell>
                <x-table.cell label="Laba" numeric>{{ Format::rupiah($report->profit) }}</x-table.cell>
            </x-table.row>
        @endforeach

        @if ($reports->hasPages())
            <x-slot:footer>{{ $reports->links() }}</x-slot:footer>
        @endif
    </x-table>
@endif
