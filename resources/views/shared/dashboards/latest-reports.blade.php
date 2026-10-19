{{--
    "Laporan terbaru" dasbor Ringkasan (Admin/OPD, 7 kolom): 5 pengajuan terakhir dari antrean verifikasi
    (MonthlyReport::verificationQueue, sama dengan tab Perlu diverifikasi). Variabel: $latestReports, $area, $class.
--}}
@use('App\Support\Format')

<x-admin.card title="Laporan terbaru" description="Menunggu verifikasi, pengajuan terakhir di atas" flush stretch :more="route($area.'.verifications.index')" :class="$class ?? null">
    @if ($latestReports->isEmpty())
        <x-admin.empty bare icon="clipboard-document-check" title="Tidak ada laporan yang menunggu verifikasi" description="Laporan yang diajukan kelompok akan muncul di sini." class="flex-1 rounded-b-box" />
    @else
        <x-admin.table bare clickable>
            <x-slot:head>
                <x-admin.table.heading>Tanggal</x-admin.table.heading>
                <x-admin.table.heading>Kelompok</x-admin.table.heading>
                <x-admin.table.heading>Periode</x-admin.table.heading>
                <x-admin.table.heading>Status</x-admin.table.heading>
            </x-slot:head>
            @foreach ($latestReports as $report)
                <x-admin.table.row :href="route($area.'.reports.show', [$report, 'dari' => 'antrean'])" :for="$report->organization->name">
                    <x-admin.table.cell label="Diajukan" class="md:whitespace-nowrap"><x-admin.date :value="$report->last_submitted_at" time /></x-admin.table.cell>
                    <x-admin.table.cell primary class="md:max-w-0 md:w-full"><span class="block md:truncate" title="{{ $report->organization->name }}">{{ $report->organization->name }}</span></x-admin.table.cell>
                    <x-admin.table.cell subtitle class="md:whitespace-nowrap">{{ Format::month($report->period) }}</x-admin.table.cell>
                    <x-admin.table.cell badge>
                        <x-admin.badge-status :status="$report->status" :label="$report->resubmitted ? 'Diajukan ulang' : null" />
                    </x-admin.table.cell>
                </x-admin.table.row>
            @endforeach
        </x-admin.table>
    @endif
</x-admin.card>
