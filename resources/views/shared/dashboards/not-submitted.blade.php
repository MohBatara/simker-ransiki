{{--
    Kelompok wajib lapor yang belum mengajukan laporan periode ini, dengan tombol "Salin pesan pengingat".
    Variabel: $notSubmitted, $progress, $period, $area.
--}}
@use('App\Support\Format')
@use('App\Support\ReminderMessage')
@use('App\Support\Dashboards\ReportingPeriod')

@php
    $deadline = App\Models\MonthlyReport::deadlineFor($period);
    $total = $progress['draft'] + $progress['missing'];
@endphp

<x-card
    :title="'Belum mengajukan '.Format::month($period)"
    :description="'Tenggat '.Format::date($deadline).' · '.ReportingPeriod::deadlineDistance($deadline)"
    id="belum-melapor"
>
    @if ($notSubmitted->isEmpty())
        <p class="text-ink-soft">Semua kelompok wajib lapor sudah mengajukan laporan bulan ini.</p>
    @else
        <ul class="divide-y divide-line">
            @foreach ($notSubmitted as $row)
                @php $organization = $row['organization']; @endphp
                <li class="flex flex-wrap items-center gap-x-3 gap-y-2 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route($area.'.organizations.reports', $organization) }}" class="block truncate font-semibold text-ink hover:underline">{{ $organization->name }}</a>
                        @if ($organization->leader_name || $organization->phone)
                            <p class="text-caption text-ink-soft">
                                Ketua{{ $organization->leader_name ? ': '.$organization->leader_name : '' }}
                                @if ($organization->phone)
                                    · <a href="tel:{{ preg_replace('/[^\d+]/', '', $organization->phone) }}" class="text-brand hover:text-brand-hover">{{ $organization->phone }}</a>
                                @endif
                            </p>
                        @endif
                    </div>
                    @if ($row['report'])
                        <x-badge tone="idle">Draf</x-badge>
                    @else
                        <span class="text-ink-muted">Belum dibuat</span>
                    @endif
                    <div x-data="copyText(@js(ReminderMessage::for($organization, $period, $row['report'], auth()->user())))" class="max-sm:w-full">
                        <x-button variant="secondary" icon="clipboard-document" x-on:click="copy()" class="max-sm:w-full">
                            <span x-text="copied ? 'Pesan disalin' : 'Salin pesan pengingat'">Salin pesan pengingat</span>
                            <span class="sr-only"> untuk {{ $organization->name }}</span>
                        </x-button>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($total > $notSubmitted->count())
            <a href="{{ route($area.'.organizations.index', ['laporan' => 'belum-diajukan', 'periode' => $period->format('Y-m')]) }}" class="mt-3 inline-block font-semibold text-brand hover:text-brand-hover">
                Lihat semua ({{ $total }})
            </a>
        @endif
    @endif
</x-card>
