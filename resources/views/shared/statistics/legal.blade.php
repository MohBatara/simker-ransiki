{{--
    Statistik → Legalitas (Admin dan OPD). Kartu dan tabel dihitung per kelompok unik (EXISTS per kelompok),
    bukan per baris izin. Variabel dari Shared\StatisticsController::legal.
--}}
@use('App\Support\Format')
@use('App\Support\Dashboards\ReportingPeriod')
@use('App\Support\Dashboards\Statistics')

@php
    $of = 'dari '.Format::number($legal['total']).' kelompok';
    $columns = ['total' => 'Kelompok', 'issued' => 'Izin terbit', 'in_process' => 'Diproses', 'needs_fix' => 'Perlu perbaikan', 'without' => 'Belum punya izin'];
@endphp

<x-layouts.app
    title="Statistik"
    :description="$area === 'opd' ? 'Angka ekonomi dan legalitas kelompok binaan.' : 'Angka ekonomi dan legalitas kelompok di Distrik Ransiki.'"
>
    <x-slot:actions>
        <x-button variant="secondary" icon="arrow-down-tray" :href="route($area.'.statistics.legal.export', $filters)">Unduh Excel</x-button>
    </x-slot:actions>

    <div class="space-y-5">
        @include('shared.statistics._header', ['tab' => 'legal', 'summaryText' => Format::number($legal['total']).' kelompok'])

        <div>
            <div class="grid grid-cols-2 gap-3 md:gap-4 xl:grid-cols-4">
                <x-stat label="Punya izin terbit" :value="Format::number($legal['issued'])" :hint="$of" tone="ok" />
                <x-stat label="Izin sedang diproses" :value="Format::number($legal['in_process'])" :hint="$of" tone="wait" />
                <x-stat label="Izin perlu perbaikan" :value="Format::number($legal['needs_fix'])" :hint="$of" tone="fix" href="#perlu-perbaikan" />
                <x-stat label="Belum punya izin" :value="Format::number($legal['without'])" :hint="$of" tone="idle" href="#belum-punya-izin" />
            </div>
            <p class="mt-2 text-caption text-ink-soft">
                Satu kelompok dihitung sekali per kartu, tetapi bisa masuk lebih dari satu kartu bila punya beberapa izin.
                Belum punya izin: tidak ada izin terbit, sedang diproses, atau perlu perbaikan.
            </p>
        </div>

        <x-card :title="'Akan habis dalam '.Statistics::EXPIRING_DAYS.' hari'" description="Izin terbit yang tanggal berlakunya diisi. Izin tanpa tanggal tidak dianggap habis." flush>
            @if ($expiring->isEmpty())
                <p class="px-4 py-4 text-ink-soft md:px-5">Tidak ada izin yang akan habis dalam {{ Statistics::EXPIRING_DAYS }} hari.</p>
            @else
                <x-list flush>
                    @foreach ($expiring as $license)
                        @php $days = ReportingPeriod::daysUntil($license->expires_on->toImmutable()); @endphp
                        <x-list.item :href="route($area.'.organizations.licenses.show', [$license->organization_id, $license])">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-ink">{{ $license->organization->name }}</span>
                                <span class="block text-caption text-ink-soft">{{ $license->type }} · berlaku sampai {{ Format::date($license->expires_on) }}</span>
                            </span>
                            <x-badge :tone="$days <= 14 ? 'fix' : 'wait'">{{ $days === 0 ? 'Habis hari ini' : $days.' hari lagi' }}</x-badge>
                        </x-list.item>
                    @endforeach
                </x-list>
            @endif
        </x-card>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 lg:items-start">
            <x-card id="perlu-perbaikan" title="Izin perlu perbaikan" :description="Format::number($needsFix->count()).' kelompok'" flush>
                @if ($needsFix->isEmpty())
                    <p class="px-4 py-4 text-ink-soft md:px-5">Tidak ada izin yang perlu diperbaiki.</p>
                @else
                    <x-list flush>
                        @foreach ($needsFix as $organization)
                            <x-list.item :href="route($area.'.organizations.licenses', $organization)">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-ink">{{ $organization->name }}</span>
                                    <span class="block text-caption text-ink-soft">{{ $organization->licenses->pluck('type')->join(', ') }} · {{ $organization->village->name }}</span>
                                </span>
                            </x-list.item>
                        @endforeach
                    </x-list>
                @endif
            </x-card>

            <x-card id="belum-punya-izin" title="Belum punya izin" :description="Format::number($without->count()).' kelompok'" flush>
                @if ($without->isEmpty())
                    <p class="px-4 py-4 text-ink-soft md:px-5">Semua kelompok sudah punya atau sedang mengurus izin.</p>
                @else
                    <x-list flush>
                        @foreach ($without as $organization)
                            <x-list.item :href="route($area.'.organizations.licenses', $organization)">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-ink">{{ $organization->name }}</span>
                                    <span class="block text-caption text-ink-soft">{{ $organization->institutionType->name }} · {{ $organization->village->name }}</span>
                                </span>
                            </x-list.item>
                        @endforeach
                    </x-list>
                @endif
            </x-card>
        </div>

        @foreach (['Per jenis kelembagaan' => [$byType, 'Jenis kelembagaan'], 'Per kampung' => [$byVillage, 'Kampung']] as $title => [$rows, $first])
            <x-card :title="$title" description="Jumlah kelompok unik." flush>
                @if ($rows->isEmpty())
                    <p class="px-4 py-4 text-ink-soft md:px-5">Belum ada kelompok.</p>
                @else
                    <x-table bare class="max-md:p-4">
                        <x-slot:head>
                            <x-table.heading>{{ $first }}</x-table.heading>
                            @foreach ($columns as $label)
                                <x-table.heading numeric>{{ $label }}</x-table.heading>
                            @endforeach
                        </x-slot:head>
                        @foreach ($rows as $row)
                            <x-table.row>
                                <x-table.cell primary>{{ $row['label'] }}</x-table.cell>
                                @foreach ($columns as $key => $label)
                                    <x-table.cell :label="$label" numeric>{{ Format::number($row[$key]) }}</x-table.cell>
                                @endforeach
                            </x-table.row>
                        @endforeach
                    </x-table>
                @endif
            </x-card>
        @endforeach
    </div>
</x-layouts.app>
