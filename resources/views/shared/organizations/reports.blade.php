@use('App\Support\Format')

@if ($reports->isEmpty())
    <x-empty-state title="Belum ada laporan" :description="'Laporan bulanan dibuat oleh akun kelompok mulai '.(Format::month($organization->reporting_starts_on)).'. Bila kelompok tidak punya HP atau akun, pilih Isi laporan.'" />
@else
    <x-table clickable>
        <x-slot:head>
            <x-table.heading>Periode</x-table.heading>
            <x-table.heading>Status</x-table.heading>
            <x-table.heading>Diajukan</x-table.heading>
            <x-table.heading numeric>Laba</x-table.heading>
        </x-slot:head>

        @foreach ($reports as $report)
            {{-- Draf kelompok yang belum pernah diajukan tidak bisa dibuka peninjau. --}}
            <x-table.row :href="auth()->user()->can('view', $report) ? route($area.'.reports.show', [$report, 'dari' => 'kelompok']) : null">
                <x-table.cell primary>{{ Format::month($report->period) }}</x-table.cell>
                <x-table.cell badge>
                    <span class="inline-flex flex-wrap items-center justify-end gap-1.5 md:justify-start">
                        <x-badge :tone="$report->status->badgeColor()">{{ $report->status->reviewerLabel() }}</x-badge>
                        @if ($report->isOverdue())
                            <x-badge tone="fix">Terlambat</x-badge>
                        @endif
                    </span>
                </x-table.cell>
                <x-table.cell label="Diajukan">{{ Format::date($report->submitted_at) }}</x-table.cell>
                <x-table.cell label="Laba" numeric>{{ Format::rupiah($report->profit) }}</x-table.cell>
            </x-table.row>
        @endforeach

        @if ($reports->hasPages())
            <x-slot:footer>{{ $reports->links() }}</x-slot:footer>
        @endif
    </x-table>
@endif

@if (! empty($entryPeriods))
    {{-- "Isi laporan" atas nama kelompok (OPD pembina/Admin), dibuka dari tombol di aksi header. --}}
    <x-modal name="report-entry" title="Isi laporan atas nama kelompok" :show="$errors->has('period')">
        <form id="form-report-entry" method="POST" action="{{ route($area.'.reports.store', $organization) }}" class="space-y-4">
            @csrf
            <p>Untuk kelompok yang tidak punya HP atau akun. Nama Anda tercatat sebagai pengisi di riwayat laporan.</p>
            @if (App\Models\MonthlyReport::separatesDuties())
                <p class="text-ink-soft">Laporan yang Anda isi atau ajukan perlu diverifikasi oleh Admin atau petugas OPD lain.</p>
            @endif
            <x-field label="Bulan laporan" for="period">
                <x-select name="period" :options="$entryPeriods" :selected="old('period', array_key_first($entryPeriods))" required />
            </x-field>
        </form>
        <x-slot:footer>
            <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'report-entry')">Batal</x-button>
            <x-button type="submit" form="form-report-entry" processing="Membuka…">Isi laporan</x-button>
        </x-slot:footer>
    </x-modal>
@endif
