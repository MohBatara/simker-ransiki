@use('App\Support\Format')
@use('App\Enums\ReportingState')

@php
    $doneCount = collect($checklist)->where('done', true)->count();
    $profileComplete = $doneCount === count($checklist);
    // Draf bulan berjalan: tampil bila sudah ada, atau bila tugas utama tidak meminta mengisi laporan lain.
    $showCurrent = $current['allowed'] && ($current['report'] || ($canCreate && in_array($task['kind'], ['waiting', 'done', 'not-started'], true)));
@endphp

<x-layouts.app title="Dasbor" description="Laporan bulanan, produksi, dan tindak lanjut kelompok Anda.">
    {{-- Kartu sapaan = SATU tugas utama (KelompokSummary::task); tombolnya tidak diulang di kartu lain. --}}
    <x-admin.greeting :name="auth()->user()->name" :subtitle="$organization->name" :tone="$task['tone']" class="mb-6">
        <x-slot:task>
            <h2 class="text-card-head font-semibold text-ink" data-task="{{ $task['kind'] }}">{{ $task['title'] }}</h2>
            @if ($task['note'])
                <figure class="mt-2 max-w-2xl rounded-control border border-fix/20 bg-panel px-3 py-2">
                    <blockquote class="whitespace-pre-line break-words text-ink">{{ $task['note']['text'] }}</blockquote>
                    <figcaption class="mt-1 text-caption text-ink-2">
                        Catatan dari {{ $task['note']['by'] ?? 'peninjau' }}@if ($task['note']['date']), {{ Format::date($task['note']['date']) }}@endif.
                    </figcaption>
                </figure>
            @endif
            @if ($task['action'])
                <div class="mt-3">
                    @include('kelompok.dashboard.report-action', ['period' => $task['period'], 'report' => $task['report'], 'label' => $task['action'], 'class' => 'max-md:w-full max-md:*:w-full'])
                    @if (! $task['report'] && ! $canCreate)
                        <p class="text-caption text-ink-2">Kelompok ini sudah tutup, sehingga tidak bisa membuat laporan baru.</p>
                    @endif
                </div>
            @endif
        </x-slot:task>
    </x-admin.greeting>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
        {{-- Laporan saya: tiga bulan wajib lapor terakhir + draf bulan berjalan. --}}
        <x-admin.card title="Laporan saya" flush>
            <x-slot:actions>
                <x-link :href="route('kelompok.reports.index')">Semua laporan</x-link>
            </x-slot:actions>

            @if ($history->isEmpty())
                <p class="px-4 pb-4 text-ink-soft md:px-5 md:pb-5">Belum ada bulan yang wajib dilaporkan.</p>
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
                        <x-list.item :href="$href" data-period="{{ $row['period']->format('Y-m') }}">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-ink">{{ Format::month($row['period']) }}</span>
                                <span class="block text-caption text-ink-soft">
                                    @if ($report?->submitted_at)
                                        Diajukan {{ Format::dateShort($report->submitted_at) }}{{ $report->wasSubmittedLate() ? ', terlambat' : '' }}
                                    @else
                                        Belum diajukan
                                    @endif
                                </span>
                            </span>
                            <x-admin.badge-status :status="$row['state']" />
                        </x-list.item>
                    @endforeach
                </x-list>
            @endif

            @if ($olderOutstanding > 0)
                <p class="border-t border-line-soft px-4 py-3 text-ink-soft md:px-5">
                    {{ $olderOutstanding }} bulan sebelumnya belum diajukan.
                    <x-link :href="route('kelompok.reports.index')" inline>Lihat daftar laporan</x-link>
                </p>
            @endif

            @if ($showCurrent)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line-soft px-4 py-3 md:px-5" data-current-report>
                    <p class="text-ink">Laporan {{ Format::month($current['period']) }} sudah bisa diisi.</p>
                    @include('kelompok.dashboard.report-action', ['period' => $current['period'], 'report' => $current['report'], 'label' => $current['report'] ? 'Lanjutkan' : 'Mulai isi', 'variant' => 'secondary', 'class' => 'max-sm:w-full max-sm:*:w-full'])
                </div>
            @endif
        </x-admin.card>

        {{-- Kelengkapan profil: lengkap = satu baris; daftar centang hanya bila ada yang belum. --}}
        @if ($profileComplete)
            <x-admin.card>
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1" data-profile-complete>
                    <p class="flex items-center gap-2 font-semibold text-ink">
                        <x-heroicon-o-check-circle class="size-5 shrink-0 text-ok" aria-hidden="true" />
                        Profil lengkap
                    </p>
                    <x-link :href="route('kelompok.profile.show')">Lihat profil</x-link>
                </div>
            </x-admin.card>
        @else
            <x-admin.card title="Kelengkapan profil">
                <p class="text-ink-soft"><span class="font-semibold tabular-nums text-ink">{{ $doneCount }} dari {{ count($checklist) }}</span> lengkap</p>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-subtle" role="img" aria-label="{{ $doneCount }} dari {{ count($checklist) }} lengkap">
                    <div class="h-full rounded-full bg-brand" style="width: {{ round($doneCount / max(1, count($checklist)) * 100) }}%"></div>
                </div>
                <ul class="mt-4 space-y-2">
                    @foreach ($checklist as $item)
                        <li class="flex items-start gap-2">
                            @if ($item['done'])
                                <x-heroicon-o-check-circle class="mt-0.5 size-5 shrink-0 text-ok" />
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
        @endif
    </div>

    {{-- Status tindak lanjut OPD atas masalah & kebutuhan yang pernah dilaporkan (baca-saja). --}}
    @if ($followUps->isNotEmpty())
        <x-admin.card title="Tindak lanjut masalah & kebutuhan" description="Dari laporan yang sudah diajukan. Status diubah oleh OPD atau Admin distrik." class="mt-6" flush>
            <x-list flush>
                @foreach ($followUps as $issue)
                    <x-list.item :href="route('kelompok.reports.show', $issue->monthly_report_id)">
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-ink">{{ $issue->category ? $issue->categoryLabel() : 'Tanpa kategori' }}</span>
                            <span class="block text-caption text-ink-soft">{{ $issue->kind->label() }} · laporan {{ \App\Support\Format::orEmpty(Format::month($issue->monthlyReport->period)) }}</span>
                            @if ($issue->followUps->isNotEmpty())
                                <span class="mt-0.5 line-clamp-2 block text-ink-soft break-words">{{ $issue->followUps->first()->note }}</span>
                            @endif
                            {{-- HP: pil di bawah teks agar kolom teks tidak terjepit pil panjang. --}}
                            <x-admin.badge-status :status="$issue->follow_up_status" class="mt-1.5 sm:hidden" />
                        </span>
                        <x-admin.badge-status :status="$issue->follow_up_status" class="max-sm:hidden" />
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
