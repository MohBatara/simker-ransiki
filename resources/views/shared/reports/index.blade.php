{{--
    Daftar laporan untuk peninjau. $mode: 'queue' (antrean verifikasi OPD) atau 'all' (Semua Laporan).
    Variabel: $area, $reports, $filters, $periods, $opds (Admin saja).
--}}
@use('App\Support\Format')
@use('App\Enums\ReportStatus')

@php
    $queue = $mode === 'queue';
    $url = $queue ? route('opd.verifications.index') : route($area.'.reports.index');
    $selects = $queue ? ['period'] : ['status', 'period', 'opd'];
    $active = collect($filters)->only($selects)->filter(fn ($value) => filled($value))->count();
    $chips = \App\Support\FilterChips::from($filters, [
        'q' => [null, null],
        'status' => ['Status', ReportStatus::reviewerOptions()],
        'period' => ['Periode', $periods],
        'opd' => ['OPD', ['none' => 'Tanpa OPD pembina'] + $opds],
        'menunggu' => [null, ['7' => 'Menunggu lebih dari 7 hari']],
    ]);
    $hasFilters = $chips !== [];
    $summary = $queue
        ? $reports->total().' laporan menunggu verifikasi'
        : collect([$reports->total().' laporan', $submittedCount ? $submittedCount.' menunggu verifikasi' : null])->filter()->join(' · ');
    $title = $queue ? 'Verifikasi Laporan' : 'Semua Laporan';
    $description = match (true) {
        $queue => 'Laporan yang diajukan kelompok binaan. Yang paling lama menunggu ada di atas.',
        $area === 'opd' => 'Laporan kelompok binaan. Draf baru bisa dibuka setelah diajukan.',
        default => 'Laporan seluruh kelompok di distrik. Draf baru bisa dibuka setelah diajukan.',
    };
@endphp

<x-layouts.app :title="$title" :description="$description">
    @unless ($queue)
        <x-slot:actions>
            <x-button variant="secondary" icon="arrow-down-tray" x-data x-on:click="$dispatch('open-modal', 'report-recap')">Unduh rekap</x-button>
        </x-slot:actions>
    @endunless

    <div class="space-y-4">
        <x-filter-bar
            :action="$url"
            search-label="Cari nama kelompok"
            :search="$filters['q'] ?? ''"
            :active="$active"
            :keep="collect($filters)->only(['menunggu'])->all()"
        >
            @unless ($queue)
                <x-select size="sm" name="status" :options="ReportStatus::reviewerOptions()" :selected="$filters['status'] ?? null" placeholder="Semua status" x-on:change="$el.form.requestSubmit()" aria-label="Status laporan" />
            @endunless
            <x-select size="sm" name="period" :options="$periods" :selected="$filters['period'] ?? null" placeholder="Semua periode" x-on:change="$el.form.requestSubmit()" aria-label="Periode" />
            @if ($opds)
                <x-select size="sm" name="opd" :options="['none' => 'Tanpa OPD pembina'] + $opds" :selected="$filters['opd'] ?? null" placeholder="Semua OPD" x-on:change="$el.form.requestSubmit()" aria-label="OPD pembina" />
            @endif
        </x-filter-bar>

        <x-filter-summary :summary="$summary" :chips="$chips" :clear-url="$url" />

        @if ($reports->isEmpty())
            @if ($hasFilters)
                <x-empty-state title="Tidak ada laporan yang cocok" description="Ubah kata pencarian atau hapus filter.">
                    <x-button variant="secondary" :href="$url">Hapus filter</x-button>
                </x-empty-state>
            @elseif ($queue)
                <x-empty-state title="Tidak ada laporan yang menunggu verifikasi" description="Laporan yang diajukan kelompok binaan akan muncul di sini.">
                    <x-button variant="secondary" :href="route('opd.reports.index')">Lihat semua laporan</x-button>
                </x-empty-state>
            @else
                <x-empty-state title="Belum ada laporan" description="Laporan muncul di sini setelah kelompok mulai mengisi laporan bulanan." />
            @endif
        @else
            <x-table clickable>
                <x-slot:head>
                    <x-table.heading>Kelompok</x-table.heading>
                    <x-table.heading>Periode</x-table.heading>
                    @unless ($queue)
                        <x-table.heading>Status</x-table.heading>
                    @endunless
                    @if ($queue)
                        <x-table.heading>Diajukan</x-table.heading>
                        <x-table.heading>Menunggu</x-table.heading>
                    @else
                        <x-table.heading optional>Diajukan</x-table.heading>
                        <x-table.heading numeric optional>Laba</x-table.heading>
                    @endif
                </x-slot:head>

                @foreach ($reports as $report)
                    {{-- Draf yang belum pernah diajukan tidak bisa dibuka peninjau (404), jadi barisnya tanpa tautan. --}}
                    <x-table.row :href="$report->submitted_at ? route($area.'.reports.show', $queue ? [$report, 'dari' => 'antrean'] : $report) : null">
                        <x-table.cell primary>{{ $report->organization->name }}</x-table.cell>
                        <x-table.cell subtitle>{{ Format::month($report->period) }}</x-table.cell>
                        <x-table.cell badge>
                            {{-- Di antrean semuanya "Diajukan"; yang ditandai hanya pengajuan ulang. --}}
                            @if (! $queue)
                                <span class="inline-flex flex-wrap items-center justify-end gap-1.5 md:justify-start">
                                    <x-badge :tone="$report->status->badgeColor()">{{ $report->status->reviewerLabel() }}</x-badge>
                                    @if ($report->isOverdue())
                                        <x-badge tone="fix">Terlambat</x-badge>
                                    @endif
                                </span>
                            @elseif ($report->resubmitted)
                                <x-badge tone="wait">Diajukan ulang</x-badge>
                            @endif
                        </x-table.cell>
                        @if ($queue)
                            <x-table.cell label="Diajukan">{{ Format::date($report->last_submitted_at) }}</x-table.cell>
                            <x-table.cell label="Menunggu" @class(['font-semibold text-ink' => $report->last_submitted_at?->lt(now()->subDays(App\Support\Dashboards\DistrictStats::WAITING_DAYS))])>{{ Format::waitingSince($report->last_submitted_at) }}</x-table.cell>
                        @else
                            <x-table.cell label="Diajukan" optional>
                                {{ Format::date($report->submitted_at) }}
                                @if ($report->wasSubmittedLate())
                                    <span class="block text-caption text-ink-soft">Diajukan terlambat</span>
                                @endif
                            </x-table.cell>
                            <x-table.cell label="Laba" numeric optional>{{ Format::rupiah($report->profit) }}</x-table.cell>
                        @endif
                    </x-table.row>
                @endforeach

                @if ($reports->hasPages())
                    <x-slot:footer>{{ $reports->links() }}</x-slot:footer>
                @endif
            </x-table>
        @endif
    </div>

    @unless ($queue)
        {{-- Rekap satu bulan: status laporan tiap kelompok + angka dari laporan terverifikasi. --}}
        <x-modal name="report-recap" title="Unduh rekap laporan">
            <form id="form-rekap" method="GET" action="{{ route($area.'.reports.recap') }}" class="space-y-4">
                <p>Status laporan setiap kelompok wajib lapor, lalu ringkasan angka dari laporan terverifikasi saja.</p>
                <x-field label="Bulan" for="recap-periode">
                    <x-select id="recap-periode" name="periode" :options="$recapPeriods" :selected="array_key_exists($filters['period'] ?? '', $recapPeriods) ? $filters['period'] : array_key_first($recapPeriods)" />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" type="submit" form="form-rekap" name="format" value="pdf">Unduh PDF</x-button>
                <x-button type="submit" form="form-rekap" name="format" value="xlsx">Unduh Excel</x-button>
            </x-slot:footer>
        </x-modal>
    @endunless
</x-layouts.app>
