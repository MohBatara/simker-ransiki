{{--
    Daftar laporan untuk peninjau (menu Laporan). $mode: 'queue' (tab Perlu diverifikasi) atau 'all' (tab Semua laporan).
    Variabel: $area, $reports, $filters, $periods, $opds (Admin saja).
--}}
@use('App\Support\Format')
@use('App\Enums\ReportStatus')

@php
    $queue = $mode === 'queue';
    $url = $queue ? route($area.'.verifications.index') : route($area.'.reports.index');
    $chips = \App\Support\FilterChips::from($filters, [
        'q' => [null, null],
        'status' => ['Status', ReportStatus::options()],
        'period' => ['Periode', $periods],
        'opd' => ['OPD', ['none' => 'Tanpa OPD pembina'] + $opds],
        'menunggu' => [null, ['7' => 'Menunggu lebih dari 7 hari']],
        'hanya' => [null, ['admin' => 'Hanya bisa diverifikasi Admin']],
    ]);
    $hasFilters = $chips !== [];
    $summary = $queue
        ? $reports->total().' laporan menunggu verifikasi'
        : collect([$reports->total().' laporan', $submittedCount ? $submittedCount.' menunggu verifikasi' : null])->filter()->join(' · ');
    $title = $queue ? 'Perlu diverifikasi' : 'Semua laporan';
    $description = match (true) {
        $queue && $area === 'admin' => 'Semua laporan yang diajukan kelompok dan belum ditinjau. Yang paling lama menunggu ada di atas.',
        $queue => 'Laporan yang diajukan kelompok binaan. Yang paling lama menunggu ada di atas.',
        $area === 'opd' => 'Laporan kelompok binaan. Draf baru bisa dibuka setelah diajukan.',
        default => 'Laporan seluruh kelompok di distrik. Draf baru bisa dibuka setelah diajukan.',
    };
@endphp

<x-layouts.app :title="$title" :description="$description" section="laporan" :tab="$queue ? 'verifikasi' : 'semua'">
    @unless ($queue)
        <x-slot:secondary>
            <x-button variant="secondary" icon="arrow-down-tray" x-data x-on:click="$dispatch('open-modal', 'report-recap')">Unduh rekap</x-button>
        </x-slot:secondary>
    @endunless

    @php
        $toolbarFilters = array_values(array_filter([
            $queue ? null : ['name' => 'status', 'label' => 'Status', 'options' => ReportStatus::options(), 'selected' => $filters['status'] ?? null, 'placeholder' => 'Semua status'],
            ['name' => 'period', 'label' => 'Periode', 'options' => $periods, 'selected' => $filters['period'] ?? null, 'placeholder' => 'Semua periode'],
            $opds ? ['name' => 'opd', 'label' => 'OPD pembina', 'options' => ['none' => 'Tanpa OPD pembina'] + $opds, 'selected' => $filters['opd'] ?? null, 'placeholder' => 'Semua OPD'] : null,
        ]));
    @endphp

    <x-admin.card flush>
        <x-admin.toolbar
            :title="$queue ? 'Antrean verifikasi' : 'Daftar laporan'"
            :action="$url"
            search-label="Cari nama kelompok"
            :search="$filters['q'] ?? ''"
            :filters="$toolbarFilters"
            :keep="collect($filters)->only(['menunggu', 'hanya'])->all()"
            :chips="$chips"
            :summary="$summary"
            :clear-url="$hasFilters ? $url : null"
        />

        {{-- Ditukar oleh x-admin.toolbar saat filter berubah. --}}
        <div data-swap="laporan">

        @if ($reports->isEmpty())
            @if ($hasFilters)
                <x-admin.empty bare icon="magnifying-glass" title="Tidak ada laporan yang cocok" description="Ubah kata pencarian atau hapus filter.">
                    <x-button variant="secondary" :href="$url">Hapus filter</x-button>
                </x-admin.empty>
            @elseif ($queue)
                <x-admin.empty bare icon="clipboard-document-check" title="Tidak ada laporan yang menunggu verifikasi" :description="$area === 'admin' ? 'Laporan yang diajukan kelompok akan muncul di sini.' : 'Laporan yang diajukan kelompok binaan akan muncul di sini.'">
                    <x-button variant="secondary" :href="route($area.'.reports.index')">Lihat semua laporan</x-button>
                </x-admin.empty>
            @else
                <x-admin.empty bare icon="document-text" title="Belum ada laporan" description="Laporan muncul di sini setelah kelompok mulai mengisi laporan bulanan." />
            @endif
        @else
            <x-admin.table bare clickable>
                <x-slot:head>
                    <x-admin.table.heading>Kelompok</x-admin.table.heading>
                    <x-admin.table.heading>Periode</x-admin.table.heading>
                    <x-admin.table.heading>Status</x-admin.table.heading>
                    @if ($queue)
                        <x-admin.table.heading>Diajukan</x-admin.table.heading>
                        <x-admin.table.heading>Menunggu</x-admin.table.heading>
                    @else
                        <x-admin.table.heading optional>Diajukan</x-admin.table.heading>
                        <x-admin.table.heading numeric optional>Laba</x-admin.table.heading>
                    @endif
                </x-slot:head>

                @foreach ($reports as $report)
                    {{-- Draf kelompok yang belum pernah diajukan tidak bisa dibuka peninjau (404), jadi barisnya tanpa tautan. --}}
                    @php $viewable = auth()->user()->can('view', $report); @endphp
                    {{-- Draf yang belum bisa dibuka tampil redup (ink-soft, tetap 7:1) dan tanpa tautan. --}}
                    <x-admin.table.row :for="$report->organization->name" :href="$viewable ? route($area.'.reports.show', $queue ? [$report, 'dari' => 'antrean'] : $report) : null" :class="$viewable ? null : 'text-ink-soft'" :data-draft="$viewable ? null : 'true'">
                        <x-admin.table.cell primary>{{ $report->organization->name }}</x-admin.table.cell>
                        <x-admin.table.cell subtitle>
                            {{ Format::month($report->period) }}
                            @if ($report->handled_by_me)
                                <span class="block text-caption text-ink-soft">Diisi oleh Anda, perlu pemverifikasi lain</span>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell badge>
                            {{-- Di antrean semuanya menunggu; pengajuan ulang diberi label sendiri. --}}
                            @if (! $queue)
                                <span class="inline-flex flex-wrap items-center justify-end gap-1.5 md:justify-start">
                                    <x-admin.badge-status :status="$report->status" />
                                    @unless ($viewable)
                                        <span class="text-caption">belum diajukan</span>
                                    @endunless
                                    @if ($report->isOverdue())
                                        <x-admin.badge-status tone="danger" label="Terlambat" />
                                    @endif
                                </span>
                            @else
                                <span class="inline-flex flex-wrap items-center justify-end gap-1.5 md:justify-start">
                                    <x-admin.badge-status :status="$report->status" />
                                    @if ($report->resubmitted)
                                        <span class="text-caption text-ink-soft">Diajukan ulang</span>
                                    @endif
                                </span>
                            @endif
                        </x-admin.table.cell>
                        @if ($queue)
                            <x-admin.table.cell label="Diajukan"><x-admin.date :value="$report->last_submitted_at" time /></x-admin.table.cell>
                            <x-admin.table.cell label="Menunggu" @class(['font-semibold text-ink' => $report->last_submitted_at?->lt(now()->subDays(App\Support\Dashboards\DistrictStats::WAITING_DAYS))])>{{ Format::waitingSince($report->last_submitted_at) }}</x-admin.table.cell>
                        @else
                            <x-admin.table.cell label="Diajukan" optional>
                                <x-admin.date :value="$report->submitted_at" time />
                                @if ($report->wasSubmittedLate())
                                    <span class="block text-caption text-ink-soft">Diajukan terlambat</span>
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell label="Laba" numeric optional>{{ Format::rupiah($report->profit) }}</x-admin.table.cell>
                        @endif
                    </x-admin.table.row>
                @endforeach

                <x-slot:footer>
                    <x-admin.pagination :paginator="$reports" noun="laporan" />
                </x-slot:footer>
            </x-admin.table>
        @endif
        </div>
    </x-admin.card>

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
