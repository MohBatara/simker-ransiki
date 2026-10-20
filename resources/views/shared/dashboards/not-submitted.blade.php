{{--
    Kelompok wajib lapor yang belum mengajukan laporan periode ini, dengan tombol "Salin pesan pengingat".
    Setelah tenggat lewat, semuanya terlambat: judul dan tautan "Lihat semua" ikut berubah.
    Variabel: $notSubmitted, $progress, $overdue, $period, $area.
--}}
@use('App\Support\Format')
@use('App\Support\ReminderMessage')
@use('App\Support\Dashboards\ReportingPeriod')

@php
    $deadline = App\Models\MonthlyReport::deadlineFor($period);
    $total = $progress['draft'] + $progress['missing'];
    $late = $overdue['past_deadline'];
@endphp

<x-admin.card
    :title="($late ? 'Terlambat melapor ' : 'Belum mengajukan ').Format::month($period)"
    :description="'Tenggat '.Format::date($deadline).' · '.ReportingPeriod::deadlineDistance($deadline)"
    id="belum-melapor"
    stretch
    :class="$class ?? null"
>
    @if ($notSubmitted->isEmpty())
        <p class="text-ink-soft">Semua kelompok wajib lapor sudah mengajukan laporan bulan ini.</p>
    @else
        <x-list>
            @foreach ($notSubmitted as $row)
                @php $organization = $row['organization']; @endphp
                <x-list.item class="flex-wrap gap-y-2">
                    <div class="min-w-0 flex-1">
                        <x-link :href="route($area.'.organizations.reports', $organization)" muted class="max-w-full text-ink"><span class="truncate">{{ $organization->name }}</span></x-link>
                        @if ($organization->leader_name || $organization->phone)
                            <p class="text-caption text-ink-soft">
                                Ketua{{ $organization->leader_name ? ': '.$organization->leader_name : '' }}
                                @if ($organization->phone)
                                    · <x-link :href="'tel:'.(preg_replace('/[^\d+]/', '', $organization->phone))" class="font-normal">{{ $organization->phone }}</x-link>
                                @endif
                            </p>
                        @endif
                    </div>
                    <span class="inline-flex items-center gap-1.5">
                        @if ($row['report'])
                            <x-admin.badge-status :status="App\Enums\ReportStatus::Draft" label="Draf" />
                        @else
                            <x-admin.badge-status tone="neutral" label="Belum dibuat" />
                        @endif
                        @if ($row['state'] === App\Enums\ReportingState::Overdue)
                            <x-admin.badge-status :status="$row['state']" />
                        @endif
                    </span>
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
            <x-link :href="route($area.'.organizations.index', ['laporan' => $late ? 'terlambat' : 'belum-diajukan', 'periode' => $period->format('Y-m')])" class="mt-3">
                Lihat semua ({{ $total }})
            </x-link>
        @endif
    @endif
</x-admin.card>
