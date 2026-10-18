@use('App\Support\Format')
@use('App\Enums\ReportStatus')

@php
    $doneCount = collect($checklist)->where('done', true)->count();
    $dueReport = $due['report'] ?? null;
    $dueSubmitted = $dueReport && in_array($dueReport->status, [ReportStatus::Submitted, ReportStatus::Verified], true);
    $showCurrent = $current['allowed'] && $canCreate && (! $due || $dueSubmitted || $current['report']);
@endphp

<x-layouts.app title="Dasbor" :header="false">
    <x-slot:lead>
        <x-admin.greeting :name="auth()->user()->name" :subtitle="$organization->name" />
    </x-slot:lead>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
        {{-- Kartu utama: apa yang perlu dilakukan untuk bulan wajib lapor --}}
        <x-admin.card :title="$due ? 'Laporan '.Format::month($due['period']) : 'Laporan bulanan'" class="lg:col-start-1">
            @if ($due)
                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <div>
                            @if ($dueReport)
                                <x-admin.badge-status :status="$dueReport->status" />
                            @else
                                <x-admin.badge-status tone="neutral" label="Belum dibuat" />
                            @endif
                        </div>

                        @if ($dueReport?->submitted_at)
                            <p class="text-ink-soft">
                                Diajukan {{ Format::date($dueReport->submitted_at) }}{{ $dueReport->wasSubmittedLate() ? ', terlambat' : '' }}
                            </p>
                        @else
                            {{-- Lewat tenggat: fix. Tinggal 3 hari atau kurang: wait, tebal. --}}
                            <p @class([
                                'text-fix' => $due['pastDeadline'],
                                'font-semibold text-wait' => ! $due['pastDeadline'] && $due['daysLeft'] <= 3,
                                'text-ink-soft' => ! $due['pastDeadline'] && $due['daysLeft'] > 3,
                            ])>
                                Tenggat {{ Format::date($due['deadline']) }} · {{ $due['distance'] }}
                            </p>
                        @endif
                    </div>

                    @include('kelompok.dashboard.report-action', ['period' => $due['period'], 'report' => $dueReport, 'class' => 'max-md:w-full max-md:*:w-full'])

                    @if (! $dueReport && ! $canCreate)
                        <p class="text-ink-soft">Kelompok ini sudah tutup, sehingga tidak bisa membuat laporan baru.</p>
                    @endif

                    @if ($due['earlierOutstanding'] > 0)
                        <p class="border-t border-line pt-3 text-ink-soft">
                            {{ $due['earlierOutstanding'] }} bulan sebelumnya belum diajukan.
                            <x-link :href="route('kelompok.reports.index')">Lihat daftar laporan</x-link>
                        </p>
                    @endif
                </div>
            @else
                <p class="text-ink-soft">Kewajiban lapor dimulai {{ Format::month($organization->reporting_starts_on) }}.</p>
            @endif

            @if ($showCurrent)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3">
                    <p class="text-ink-soft">
                        Laporan {{ Format::month($current['period']) }} sudah bisa mulai diisi sebagai draf.
                    </p>
                    @include('kelompok.dashboard.report-action', ['period' => $current['period'], 'report' => $current['report'], 'variant' => 'secondary'])
                </div>
            @endif
        </x-admin.card>

        @if ($revision)
            <x-alert tone="fix" :title="'Perlu revisi: Laporan '.Format::month($revision->period)" class="lg:col-start-1">
                @if ($revision->latestRevisionRequest)
                    <p class="whitespace-pre-line break-words">{{ $revision->latestRevisionRequest->note }}</p>
                    <p class="mt-1 text-caption text-ink-soft">
                        Catatan dari {{ $revision->latestRevisionRequest->user?->name ?? 'peninjau' }}, {{ Format::date($revision->latestRevisionRequest->created_at) }}.
                    </p>
                @endif
                <div class="mt-3">
                    <x-button variant="secondary" :href="route('kelompok.reports.edit', $revision)">Perbaiki laporan</x-button>
                </div>
            </x-alert>
        @endif

        {{-- Kelengkapan profil: di HP setelah kartu utama, di desktop kolom kanan --}}
        <x-admin.card title="Kelengkapan profil" class="lg:col-start-2 lg:row-span-2 lg:row-start-1">
            <p class="text-ink-soft"><span class="font-semibold tabular-nums text-ink">{{ $doneCount }} dari {{ count($checklist) }}</span> lengkap</p>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-subtle" role="img" aria-label="{{ $doneCount }} dari {{ count($checklist) }} lengkap">
                <div class="h-full rounded-full bg-brand" style="width: {{ round($doneCount / max(1, count($checklist)) * 100) }}%"></div>
            </div>
            <ul class="mt-4 space-y-2">
                @foreach ($checklist as $item)
                    <li class="flex items-start gap-2">
                        @if ($item['done'])
                            <x-heroicon-o-check-circle class="mt-0.5 size-5 shrink-0 text-brand" />
                            <span class="text-ink">{{ $item['label'] }}</span>
                            <span class="sr-only">sudah lengkap</span>
                        @else
                            <span class="mt-0.5 size-5 shrink-0 rounded-full border border-line-input" aria-hidden="true"></span>
                            <span class="min-w-0">
                                <span class="text-ink">{{ $item['label'] }}</span>
                                <span class="sr-only">belum lengkap.</span>
                                <x-link :href="route($item['route'])" class="ml-1">Lengkapi</x-link>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-admin.card>
    </div>

    {{-- Selebar grid grafik di bawahnya. --}}
    <x-admin.card title="Riwayat laporan" class="mt-6" flush>
        <x-slot:actions>
            <x-link :href="route('kelompok.reports.index')">Semua laporan</x-link>
        </x-slot:actions>

        @if ($history->isEmpty())
            <p class="px-4 py-4 text-ink-soft md:px-5">Belum ada bulan yang wajib dilaporkan.</p>
        @else
            <x-list flush>
                @foreach ($history as $row)
                    @php
                        $report = $row['report'];
                        $href = match (true) {
                            $report === null => route('kelompok.reports.index'),
                            $report->status->isEditable() => route('kelompok.reports.edit', $report),
                            default => route('kelompok.reports.show', $report),
                        };
                    @endphp
                    <x-list.item :href="$href">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-ink">{{ Format::month($row['period']) }}</span>
                                @if ($report?->wasSubmittedLate())
                                    <span class="block text-caption text-ink-soft">Diajukan terlambat</span>
                                @endif
                            </span>
                            @if ($report)
                                <x-admin.badge-status :status="$report->status" />
                            @else
                                <span class="text-ink-muted">Belum dibuat</span>
                            @endif
                    </x-list.item>
                @endforeach
            </x-list>
        @endif
    </x-admin.card>

    {{-- Status tindak lanjut OPD atas masalah & kebutuhan yang pernah dilaporkan (baca-saja). --}}
    @if ($followUps->isNotEmpty())
        <x-admin.card title="Tindak lanjut masalah & kebutuhan" description="Dari laporan yang sudah diajukan. Status diubah oleh OPD atau Admin distrik." class="mt-6" flush>
            <x-list flush>
                @foreach ($followUps as $issue)
                    <x-list.item :href="route('kelompok.reports.show', $issue->monthly_report_id)">
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-ink">{{ $issue->category ? $issue->categoryLabel() : 'Tanpa kategori' }}</span>
                            <span class="block text-caption text-ink-soft">{{ $issue->kind->label() }} · laporan {{ Format::month($issue->monthlyReport->period) }}</span>
                            @if ($issue->followUps->isNotEmpty())
                                <span class="mt-0.5 line-clamp-2 block text-ink-soft break-words">{{ $issue->followUps->first()->note }}</span>
                            @endif
                        </span>
                        <x-admin.badge-status :status="$issue->follow_up_status" />
                    </x-list.item>
                @endforeach
            </x-list>
        </x-admin.card>
    @endif

    {{-- Grafik 12 bulan sampai bulan wajib lapor, hanya dari laporan terverifikasi kelompok ini. --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-stretch">
        <x-chart
            title="Pendapatan penjualan, 12 bulan"
            :chart="App\Support\Dashboards\StatisticsCharts::incomeTrend($incomeTrend, coverage: false)"
            note="Bulan tanpa laporan terverifikasi tidak digambar (garis terputus), bukan dianggap 0."
        />
        <x-chart
            title="Produksi, 12 bulan"
            :chart="App\Support\Dashboards\StatisticsCharts::productionTrend($productionTrend, $months)"
        />
    </div>
</x-layouts.app>
