@use('App\Support\Format')
@use('App\Enums\ReportStatus')

@php
    $doneCount = collect($checklist)->where('done', true)->count();
    $dueReport = $due['report'] ?? null;
    $dueSubmitted = $dueReport && in_array($dueReport->status, [ReportStatus::Submitted, ReportStatus::Verified], true);
    $showCurrent = $current['allowed'] && $canCreate && (! $due || $dueSubmitted || $current['report']);
@endphp

<x-layouts.app :title="$organization->name" eyebrow="Dasbor">
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
        {{-- Kartu utama: apa yang perlu dilakukan untuk bulan wajib lapor --}}
        <x-card :title="$due ? 'Laporan '.Format::month($due['period']) : 'Laporan bulanan'" class="lg:col-start-1">
            @if ($due)
                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <div>
                            @if ($dueReport)
                                <x-badge :tone="$dueReport->status->badgeColor()">{{ $dueReport->status->label() }}</x-badge>
                            @else
                                <x-badge>Belum dibuat</x-badge>
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
                            <a href="{{ route('kelompok.reports.index') }}" class="font-semibold text-brand hover:text-brand-hover">Lihat daftar laporan</a>
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
        </x-card>

        @if ($revision)
            <x-alert tone="fix" :title="'Perlu revisi: Laporan '.Format::month($revision->period)" class="lg:col-start-1">
                @if ($revision->latestRevisionRequest)
                    <p class="whitespace-pre-line">{{ $revision->latestRevisionRequest->note }}</p>
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
        <x-card title="Kelengkapan profil" class="lg:col-start-2 lg:row-span-3 lg:row-start-1">
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
                                <a href="{{ route($item['route']) }}" class="ml-1 font-semibold text-brand hover:text-brand-hover">Lengkapi</a>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card title="Riwayat laporan" class="lg:col-start-1" flush>
            <x-slot:actions>
                <a href="{{ route('kelompok.reports.index') }}" class="rounded-control font-semibold text-brand hover:text-brand-hover">Semua laporan</a>
            </x-slot:actions>

            @if ($history->isEmpty())
                <p class="px-4 py-4 text-ink-soft md:px-5">Belum ada bulan yang wajib dilaporkan.</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($history as $row)
                        @php
                            $report = $row['report'];
                            $href = match (true) {
                                $report === null => route('kelompok.reports.index'),
                                $report->status->isEditable() => route('kelompok.reports.edit', $report),
                                default => route('kelompok.reports.show', $report),
                            };
                        @endphp
                        <li>
                            <a href="{{ $href }}" class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-subtle md:px-5">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-ink">{{ Format::month($row['period']) }}</span>
                                    @if ($report?->wasSubmittedLate())
                                        <span class="block text-caption text-ink-soft">Diajukan terlambat</span>
                                    @endif
                                </span>
                                @if ($report)
                                    <x-badge :tone="$report->status->badgeColor()">{{ $report->status->label() }}</x-badge>
                                @else
                                    <span class="text-ink-muted">Belum dibuat</span>
                                @endif
                                <x-heroicon-o-chevron-right class="size-4 shrink-0 text-ink-muted" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.app>
