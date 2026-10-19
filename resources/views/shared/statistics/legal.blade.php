{{--
    Dasbor → Legalitas (Admin dan OPD). Kartu dan tabel dihitung per kelompok unik (EXISTS per kelompok),
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
    title="Legalitas"
    :description="$area === 'opd' ? 'Izin dan status hukum kelompok binaan.' : 'Izin dan status hukum kelompok di Distrik Ransiki.'"
    section="dasbor"
    tab="legalitas"
    :tab-urls="['ekonomi' => route($area.'.statistics.economy', $filters), 'legalitas' => route($area.'.statistics.legal', $filters)]"
>
    <div class="space-y-6">
        @include('shared.statistics._header', ['tab' => 'legal', 'summaryText' => Format::number($legal['total']).' kelompok'])

        {{-- Ditukar oleh x-admin.toolbar saat filter berubah. --}}
        <div data-swap="statistik" class="space-y-6">

        <div>
            <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <x-admin.stat compact icon="document-check" label="Punya izin terbit" :value="Format::number($legal['issued'])" :hint="$of" tone="success" />
                <x-admin.stat compact icon="arrow-path" label="Izin sedang diproses" :value="Format::number($legal['in_process'])" :hint="$of" tone="warning" />
                <x-admin.stat compact icon="wrench-screwdriver" label="Izin perlu perbaikan" :value="Format::number($legal['needs_fix'])" :hint="$of" tone="danger" href="#perlu-perbaikan" />
                <x-admin.stat compact icon="document-minus" label="Belum punya izin" :value="Format::number($legal['without'])" :hint="$of" href="#belum-punya-izin" />
            </div>
            <p class="mt-2 text-caption text-ink-soft">
                Satu kelompok dihitung sekali per kartu, tetapi bisa masuk lebih dari satu kartu bila punya beberapa izin.
                Belum punya izin: tidak ada izin terbit, sedang diproses, atau perlu perbaikan.
            </p>
        </div>

        <x-admin.card :title="'Akan habis dalam '.Statistics::EXPIRING_DAYS.' hari'" description="Izin terbit yang tanggal berlakunya diisi. Izin tanpa tanggal tidak dianggap habis." flush>
            @if ($expiring->isEmpty())
                <p class="px-4 py-4 text-ink-soft md:px-5">Tidak ada izin yang akan habis dalam {{ Statistics::EXPIRING_DAYS }} hari.</p>
            @else
                <x-list flush>
                    @foreach ($expiring as $license)
                        @php $days = ReportingPeriod::daysUntil($license->expires_on->toImmutable()); @endphp
                        <x-list.item :href="route($area.'.organizations.licenses.show', [$license->organization_id, $license])">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-ink">{{ $license->organization->name }}</span>
                                <span class="block text-caption text-ink-soft">{{ $license->type }} · berlaku sampai {{ \App\Support\Format::orEmpty(Format::date($license->expires_on)) }}</span>
                            </span>
                            <x-admin.badge-status :tone="$days <= 14 ? 'danger' : 'warning'" :label="$days === 0 ? 'Habis hari ini' : $days.' hari lagi'" />
                        </x-list.item>
                    @endforeach
                </x-list>
            @endif
        </x-admin.card>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
            <x-admin.card id="perlu-perbaikan" title="Izin perlu perbaikan" :description="Format::number($needsFix->count()).' kelompok'" flush>
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
            </x-admin.card>

            <x-admin.card id="belum-punya-izin" title="Belum punya izin" :description="Format::number($without->count()).' kelompok'" flush>
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
            </x-admin.card>
        </div>

        @foreach (['Per jenis kelembagaan' => [$byType, 'Jenis kelembagaan'], 'Per kampung' => [$byVillage, 'Kampung']] as $title => [$rows, $first])
            <x-admin.card :title="$title" description="Jumlah kelompok unik." flush>
                @if ($rows->isEmpty())
                    <p class="px-4 py-4 text-ink-soft md:px-5">Belum ada kelompok.</p>
                @else
                    <x-admin.table bare :label="'Legalitas '.mb_strtolower($title)" class="max-md:p-4">
                        <x-slot:head>
                            <x-admin.table.heading wrap>{{ $first }}</x-admin.table.heading>
                            @foreach ($columns as $label)
                                <x-admin.table.heading numeric wrap>{{ $label }}</x-admin.table.heading>
                            @endforeach
                        </x-slot:head>
                        @foreach ($rows as $row)
                            <x-admin.table.row>
                                <x-admin.table.cell primary>{{ $row['label'] }}</x-admin.table.cell>
                                @foreach ($columns as $key => $label)
                                    <x-admin.table.cell :label="$label" numeric>{{ \App\Support\Format::orEmpty(Format::number($row[$key])) }}</x-admin.table.cell>
                                @endforeach
                            </x-admin.table.row>
                        @endforeach
                    </x-admin.table>
                @endif
            </x-admin.card>
        @endforeach
        </div>
    </div>
</x-layouts.app>
