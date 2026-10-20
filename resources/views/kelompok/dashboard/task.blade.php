{{--
    Kotak tugas utama di dalam kartu sapaan dasbor Kelompok (KelompokSummary::task): putih, bingkai line, sudut 12px, garis
    aksen 4px di kiri berwarna sesuai keadaan (merah revisi/terlambat, kuning menunggu, selain itu brand), ikon status di
    kiri. Tombol utama di kanan (desktop, sejajar tengah); di HP di bawah selebar penuh.
    Variabel: $task, $canCreate.
--}}
@use('App\Support\Format')

@php
    [$accent, $iconBox] = match ($task['tone']) {
        'danger' => ['bg-fix', 'bg-fix-bg text-fix'],
        'warning' => ['bg-wait', 'bg-wait-bg text-wait'],
        default => ['bg-brand', 'bg-brand-50 text-brand'],
    };
    $icon = match ($task['kind']) {
        'revision' => 'exclamation-triangle',
        'overdue' => 'exclamation-circle',
        'open' => 'pencil-square',
        'waiting' => 'clock',
        'done' => 'check-circle',
        default => 'calendar-days',
    };
@endphp

<div class="relative overflow-hidden rounded-box border border-line bg-panel" data-task-box data-tone="{{ $task['tone'] }}">
    <span class="absolute inset-y-0 left-0 w-1 {{ $accent }}" aria-hidden="true"></span>
    <div class="flex flex-col gap-4 py-4 pl-5 pr-4 md:flex-row md:items-center md:gap-6 md:pr-5">
        <div class="flex min-w-0 flex-1 items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $iconBox }}" aria-hidden="true">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5" />
            </span>
            <div class="min-w-0 flex-1 md:self-center">
                <h2 class="text-card-head font-semibold text-ink" data-task="{{ $task['kind'] }}">{{ $task['title'] }}</h2>
                @if ($task['note'])
                    <blockquote class="mt-1 whitespace-pre-line break-words text-ui text-ink">{{ $task['note']['text'] }}</blockquote>
                    <p class="mt-1 text-label text-ink-3">
                        {{ $task['note']['by'] ?? 'Peninjau' }}@if ($task['note']['date']) · {{ Format::dateShort($task['note']['date']) }}@endif
                    </p>
                @endif
                @if ($task['action'] && ! $task['report'] && ! $canCreate)
                    <p class="mt-1 text-caption text-ink-2">Kelompok ini sudah tutup, sehingga tidak bisa membuat laporan baru.</p>
                @endif
            </div>
        </div>
        @if ($task['action'])
            <div class="shrink-0 max-md:w-full max-md:*:w-full">
                @include('kelompok.dashboard.report-action', ['period' => $task['period'], 'report' => $task['report'], 'label' => $task['action'], 'class' => 'max-md:w-full max-md:*:w-full'])
            </div>
        @endif
    </div>
</div>
