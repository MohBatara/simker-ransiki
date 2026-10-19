@use('App\Support\Format')
@use('App\Enums\OrganizationStatus')

<x-layouts.app
    title="Laporan bulanan"
    :description="'Satu laporan untuk setiap bulan. Tenggat: tanggal '.config('simker.reports.deadline_day').' bulan berikutnya, pukul 23.59 WIT.'"
>
    @if ($organization->status === OrganizationStatus::Closed)
        <x-alert class="mb-6">Kelompok ini sudah tutup, sehingga tidak bisa membuat laporan baru. Draf atau laporan yang diminta revisi tetap bisa diselesaikan.</x-alert>
    @endif

    @error('period')
        <x-alert tone="fix" class="mb-6">{{ $message }}</x-alert>
    @enderror

    @if ($rows->isEmpty())
        <x-admin.empty
            icon="document-text"
            title="Belum ada bulan yang wajib dilaporkan"
            :description="'Kewajiban lapor dimulai '.(Format::month($organization->reporting_starts_on)).'. Laporan pertama bisa dibuat mulai bulan itu.'"
        />
    @else
        <x-admin.table clickable>
            <x-slot:head>
                <x-admin.table.heading>Periode</x-admin.table.heading>
                <x-admin.table.heading fit>Status</x-admin.table.heading>
                <x-admin.table.heading fit>Tenggat</x-admin.table.heading>
                <x-admin.table.heading fit>Diajukan</x-admin.table.heading>
                <x-admin.table.heading><span class="sr-only">Tindakan</span></x-admin.table.heading>
            </x-slot:head>

            @foreach ($rows as $row)
                @php
                    $report = $row['report'];
                    $href = $report ? ($report->status->isEditable() ? route('kelompok.reports.edit', $report) : route('kelompok.reports.show', $report)) : null;
                @endphp
                <x-admin.table.row :href="$href">
                    <x-admin.table.cell primary><span class="whitespace-nowrap" title="{{ Format::month($row['period']) }}">{{ Format::monthShort($row['period']) }}</span></x-admin.table.cell>
                    <x-admin.table.cell badge>
                        <span class="inline-flex flex-wrap items-center justify-end gap-1.5 md:justify-start">
                            @if ($report)
                                <x-admin.badge-status :status="$report->status" />
                            @else
                                <x-admin.badge-status tone="neutral" label="Belum dibuat" />
                            @endif
                            @if ($row['overdue'])
                                <x-admin.badge-status tone="danger" label="Terlambat" />
                            @endif
                        </span>
                    </x-admin.table.cell>
                    <x-admin.table.cell label="Tenggat" fit><x-admin.date :value="$row['deadline']" /></x-admin.table.cell>
                    <x-admin.table.cell label="Diajukan" fit>
                        <x-admin.date :value="$report?->submitted_at" time />
                        @if ($report?->wasSubmittedLate())
                            <span class="block text-caption text-ink-soft">Diajukan terlambat</span>
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell @class(['md:text-right', 'max-md:hidden' => $report || ! $canCreate]) :actions="! $report && $canCreate">
                        @if (! $report && $canCreate)
                            <form method="POST" action="{{ route('kelompok.reports.store') }}" class="relative z-10">
                                @csrf
                                <input type="hidden" name="period" value="{{ $row['period']->format('Y-m') }}">
                                <x-button type="submit" variant="secondary" class="max-md:w-full" processing="Membuat…">Buat laporan</x-button>
                            </form>
                        @endif
                    </x-admin.table.cell>
                </x-admin.table.row>
            @endforeach

            @if ($rows->hasPages())
                <x-slot:footer><x-admin.pagination :paginator="$rows" noun="bulan" /></x-slot:footer>
            @endif
        </x-admin.table>
    @endif
</x-layouts.app>
