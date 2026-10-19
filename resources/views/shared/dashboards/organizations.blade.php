{{--
    Kartu "Kelompok" dasbor Admin, tab Ringkasan (4 kolom): donat jenis kelembagaan dengan total di tengah, legenda chip
    berbingkai (5 jenis teratas + Lainnya, menaut ke daftar terfilter), lalu pil status (Aktif, Tidak aktif, Ditutup).
    Variabel: $organizations (DistrictStats::organizations()), $class (opsional).
--}}
@use('App\Support\Format')
@use('App\Enums\OrganizationStatus')

@php
    $total = $organizations['total'];
    $types = collect($organizations['types'])->where('value', '>', 0)->values();
    $top = $types->take(5)->values();
    $rest = $types->slice(5);
    $tones = ['chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5'];
    $slices = $top->map(fn ($type, $index) => [
        'label' => $type['label'],
        'value' => $type['value'],
        'tone' => $tones[$index],
        'href' => route('admin.organizations.index', ['type' => $type['id']]),
    ]);
    if ($rest->isNotEmpty()) {
        $slices->push(['label' => 'Lainnya', 'value' => $rest->sum('value'), 'tone' => 'chart-other', 'href' => route('admin.organizations.index')]);
    }
    $statuses = collect([
        [OrganizationStatus::Active, 'Aktif'],
        [OrganizationStatus::Inactive, 'Tidak aktif'],
        [OrganizationStatus::Closed, 'Ditutup'],
    ]);
    $config = ['type' => 'doughnut', 'labels' => $slices->pluck('label')->all(), 'data' => $slices->pluck('value')->all(), 'tones' => $slices->pluck('tone')->all()];
    $summary = 'Kelompok per jenis kelembagaan: '.$slices->map(fn ($slice) => $slice['label'].' '.$slice['value'])->join(', ').'.';
    // Kelas statis agar Tailwind membangun warna palet grafik untuk titik legenda.
    $dots = ['chart-1' => 'bg-chart-1', 'chart-2' => 'bg-chart-2', 'chart-3' => 'bg-chart-3', 'chart-4' => 'bg-chart-4', 'chart-5' => 'bg-chart-5', 'chart-other' => 'bg-chart-other'];
@endphp

<x-admin.card title="Kelompok" description="Menurut jenis kelembagaan" stretch :more="route('admin.organizations.index')" :class="$class ?? null">
    <div class="flex flex-1 flex-col gap-5">
        @if ($slices->isEmpty())
            <p class="text-caption text-ink-2">Belum ada kelompok terdaftar.</p>
        @else
            <div x-data="chart(@js([$config]))" class="relative mx-auto size-44">
                <canvas data-panel="0" role="img" aria-label="{{ $summary }}">{{ $summary }}</canvas>
                <p class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-[1.75rem] leading-8 font-semibold tabular-nums text-ink">{{ \App\Support\Format::orEmpty(Format::number($total)) }}</span>
                    <span class="text-label text-ink-2">kelompok</span>
                </p>
            </div>

            <ul class="flex flex-wrap gap-1.5" aria-label="Jenis kelembagaan">
                @foreach ($slices as $slice)
                    <li class="min-w-0">
                        <a href="{{ $slice['href'] }}" class="inline-flex h-11 max-w-full items-center gap-1.5 rounded-pill border border-line px-2 text-label text-ink-2 transition-colors hover:border-brand-100 hover:bg-hover md:h-7">
                            <span class="{{ $dots[$slice['tone']] }} size-2 shrink-0 rounded-full" aria-hidden="true"></span>
                            <span class="truncate">{{ $slice['label'] }}</span>
                            <span class="font-semibold tabular-nums text-ink">{{ \App\Support\Format::orEmpty(Format::number($slice['value'])) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Status kelompok sebagai pil lembut; tiap pil menaut ke daftar Kelompok terfilter. --}}
        <ul class="mt-auto flex flex-wrap gap-2 border-t border-line-soft pt-4" aria-label="Status kelompok">
            @foreach ($statuses as [$status, $label])
                <li>
                    <a href="{{ route('admin.organizations.index', ['status' => $status->value]) }}" class="inline-flex min-h-11 items-center rounded-pill md:min-h-0" title="{{ $status->label() }}">
                        <x-admin.badge-status :tone="App\Support\StatusTone::of($status)" class="gap-1 hover:underline">{{ $label }} <span class="tabular-nums">{{ \App\Support\Format::orEmpty(Format::number($organizations['statuses'][$status->value] ?? 0)) }}</span></x-admin.badge-status>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</x-admin.card>
