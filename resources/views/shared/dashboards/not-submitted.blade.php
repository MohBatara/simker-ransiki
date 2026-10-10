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
        <x-list>
            @foreach ($notSubmitted as $row)
                @php $organization = $row['organization']; @endphp
                <x-list.item class="flex-wrap gap-y-2">
                    <div class="min-w-0 flex-1">
                        <x-link href="{{ route($area.'.organizations.reports', $organization) }}" muted class="max-w-full text-ink"><span class="truncate">{{ $organization->name }}</span></x-link>
                        @if ($organization->leader_name || $organization->phone)
                            <p class="text-caption text-ink-soft">
                                Ketua{{ $organization->leader_name ? ': '.$organization->leader_name : '' }}
                                @if ($organization->phone)
                                    · <x-link href="tel:{{ preg_replace('/[^\d+]/', '', $organization->phone) }}" class="font-normal">{{ $organization->phone }}</x-link>
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
                </x-list.item>
            @endforeach
        </x-list>
        @if ($total > $notSubmitted->count())
            <x-link href="{{ route($area.'.organizations.index', ['laporan' => 'belum-diajukan', 'periode' => $period->format('Y-m')]) }}" class="mt-3">
                Lihat semua ({{ $total }})
            </x-link>
        @endif
    @endif
</x-card>
