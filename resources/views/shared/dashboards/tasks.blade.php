{{--
    "Perlu dikerjakan" dasbor Ringkasan (Admin/OPD, 5 kolom): grid 2x2 kotak progres pelaporan periode terpilih (dari
    ReportingStats::progress dan ::overdue, tanpa query baru), tiap kotak menaut ke daftar terkait. "Lihat semua" membuka
    daftar tugas berprioritas (hanya yang jumlahnya > 0) dengan tombol aksi langsung.
    Variabel: $progress, $overdue, $period, $area, $tasks (list of ['icon', 'title', 'hint' => ?string, 'action', 'href']).
--}}
@use('App\Support\Format')
@use('App\Support\Dashboards\ReportingPeriod')

@php
    $month = $period->format('Y-m');
    $total = $progress['total'];
    $submitted = $progress['verified'] + $progress['submitted'] + $progress['revision'];
    $boxes = [
        ['icon' => 'paper-airplane', 'title' => 'Sudah mengajukan', 'value' => $submitted, 'verb' => 'mengajukan', 'icon_class' => 'bg-brand-50 text-brand', 'fill' => 'bg-brand-100', 'href' => route($area.'.reports.index', ['period' => $month])],
        ['icon' => 'check-badge', 'title' => 'Terverifikasi', 'value' => $progress['verified'], 'verb' => 'terverifikasi', 'icon_class' => 'bg-ok-bg text-ok', 'fill' => 'bg-chart-3', 'href' => route($area.'.reports.index', ['status' => 'verified', 'period' => $month])],
        ['icon' => 'clock', 'title' => 'Menunggu verifikasi', 'value' => $progress['submitted'], 'verb' => 'menunggu', 'icon_class' => 'bg-wait-bg text-wait', 'fill' => 'bg-sand', 'href' => route($area.'.reports.index', ['status' => 'submitted', 'period' => $month])],
        ['icon' => 'exclamation-triangle', 'title' => 'Terlambat melapor', 'value' => $overdue['total'], 'verb' => 'terlambat', 'icon_class' => 'bg-fix-bg text-fix', 'fill' => 'bg-fix-bg', 'href' => route($area.'.organizations.index', ['laporan' => 'terlambat', 'periode' => $month])],
    ];
@endphp

<x-admin.card title="Perlu dikerjakan" :description="'Pelaporan '.Format::month($period).', '.Format::number($total).' kelompok wajib lapor'" stretch :class="$class ?? null">
    <div x-data="{ all: false }" class="flex flex-1 flex-col gap-4">
        <ul class="grid grid-cols-2 gap-2 md:gap-3">
            @foreach ($boxes as $box)
                @php $percent = $total > 0 ? (int) round($box['value'] / $total * 100) : 0; @endphp
                <li class="min-w-0">
                    <a href="{{ $box['href'] }}" class="flex h-full flex-col gap-2.5 rounded-box border border-line p-3 transition-colors hover:border-brand-100 hover:bg-hover md:p-4">
                        <span class="flex items-center gap-2">
                            <span class="{{ $box['icon_class'] }} flex size-8 shrink-0 items-center justify-center rounded-control" aria-hidden="true">
                                <x-dynamic-component :component="'heroicon-o-'.$box['icon']" class="size-4" />
                            </span>
                            <span class="min-w-0 text-caption font-semibold text-ink">{{ $box['title'] }}</span>
                        </span>
                        <span class="relative block h-5 overflow-hidden rounded-pill bg-subtle" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="{{ $box['title'] }}">
                            <span class="{{ $box['fill'] }} absolute inset-y-0 left-0 rounded-pill" style="width: {{ min(100, $percent) }}%"></span>
                            <span class="relative flex h-full items-center px-2 text-label font-semibold tabular-nums text-ink">{{ $percent }}%</span>
                        </span>
                        <span class="text-label text-ink-3"><span class="font-semibold tabular-nums text-ink">{{ Format::number($box['value']) }}</span> dari {{ Format::number($total) }} {{ $box['verb'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <p class="text-label text-ink-3">Tenggat {{ Format::date($overdue['deadline']) }}, {{ ReportingPeriod::deadlineDistance($overdue['deadline']) }}.</p>

        @if ($tasks === [])
            <p class="mt-auto flex items-center gap-2 text-caption text-ink-2">
                <x-heroicon-o-check-circle class="size-5 shrink-0 text-ok" aria-hidden="true" />
                Tidak ada yang perlu dikerjakan sekarang. Semua laporan yang diajukan sudah ditinjau.
            </p>
        @else
            <ol x-cloak x-show="all" id="daftar-tugas" class="divide-y divide-line-soft rounded-box border border-line">
                @foreach ($tasks as $task)
                    <li class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center">
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-control bg-brand-50 text-brand" aria-hidden="true">
                                <x-dynamic-component :component="'heroicon-o-'.$task['icon']" class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-caption font-semibold text-ink">{{ $task['title'] }}</p>
                                @if ($task['hint'] ?? null)
                                    <p class="mt-0.5 text-label text-ink-2">{{ $task['hint'] }}</p>
                                @endif
                            </div>
                        </div>
                        <x-button :href="$task['href']" :variant="$loop->first ? 'primary' : 'secondary'" class="max-sm:w-full">{{ $task['action'] }}</x-button>
                    </li>
                @endforeach
            </ol>
            <button type="button" x-on:click="all = ! all" x-bind:aria-expanded="all" aria-controls="daftar-tugas" class="mt-auto inline-flex min-h-11 items-center gap-1 self-start text-caption font-medium text-brand hover:text-brand-hover hover:underline md:min-h-8">
                <span x-text="all ? 'Tutup daftar tugas' : 'Lihat semua ({{ count($tasks) }} tugas)'">Lihat semua ({{ count($tasks) }} tugas)</span>
                <x-heroicon-o-arrow-right class="size-3.5" aria-hidden="true" x-bind:class="all && 'rotate-90'" />
            </button>
        @endif
    </div>
</x-admin.card>
