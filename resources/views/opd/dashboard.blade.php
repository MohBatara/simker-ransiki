@use('App\Support\Format')
@use('App\Support\Dashboards\ReportingPeriod')

<x-layouts.app :title="$user->opd->name" eyebrow="Dasbor" :banner="$banner">
    <x-slot:actions>
        @include('shared.dashboards.period-picker')
    </x-slot:actions>

    <div class="space-y-5">
        <div class="grid gap-5 lg:grid-cols-3 lg:items-start">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-1">
                @include('shared.dashboards.overdue-stat', ['area' => 'opd'])
                @include('shared.dashboards.stale-needs-stat', ['area' => 'opd'])
            </div>

            {{-- Yang perlu dilakukan lebih dulu --}}
            <x-card title="Perlu tindakan" class="lg:col-span-2 lg:col-start-1 lg:row-start-1">
                @if ($waitingCount === 0)
                    <p class="text-ink-soft">Tidak ada laporan yang menunggu verifikasi.</p>
                @else
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <a href="{{ route('opd.verifications.index') }}" class="group rounded-control">
                            <span class="text-page-title font-semibold tabular-nums text-wait">{{ $waitingCount }}</span>
                            <span class="text-ink group-hover:underline">laporan menunggu verifikasi</span>
                        </a>
                        <x-button :href="route('opd.verifications.index')" class="max-md:w-full">Buka antrean</x-button>
                    </div>
                    <dl class="mt-4 space-y-2 border-t border-line pt-3">
                        @if ($oldestWaiting)
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-ink-soft">Paling lama menunggu:</dt>
                                <dd>
                                    <x-link :href="route('opd.reports.show', [$oldestWaiting, 'dari' => 'antrean'])">
                                        {{ $oldestWaiting->organization->name }}, {{ Format::month($oldestWaiting->period) }}
                                    </x-link>
                                    <span class="text-ink-soft">· {{ Format::waitingSince($oldestWaiting->last_submitted_at) }}</span>
                                </dd>
                            </div>
                        @endif
                        @if ($waitingLong > 0)
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-ink-soft">Menunggu lebih dari 7 hari:</dt>
                                <dd><x-link :href="route('opd.reports.index', ['status' => 'submitted', 'menunggu' => 7])" class="tabular-nums">{{ $waitingLong }} laporan</x-link></dd>
                            </div>
                        @endif
                    </dl>
                @endif
            </x-card>
        </div>

        @include('shared.dashboards.progress', ['area' => 'opd'])

        <div class="grid gap-5 lg:grid-cols-2 lg:items-start">
            @include('shared.dashboards.not-submitted', ['area' => 'opd'])

            <x-card :title="'Masalah & kebutuhan, '.Format::month($period)" description="Jumlah kelompok per kategori, dari laporan yang sudah diajukan.">
                <div class="space-y-5">
                    @foreach (['problem' => 'Masalah', 'support' => 'Kebutuhan'] as $kind => $title)
                        <section>
                            <h3 class="mb-2 text-ui font-semibold text-ink">{{ $title }}</h3>
                            <x-bar-list
                                :items="$topIssues[$kind]->map(fn ($row) => [
                                    'label' => $row['label'],
                                    'value' => $row['organizations'],
                                    'href' => route('opd.issues.index', ['kind' => $kind, 'category' => $row['category'], 'period' => $period->format('Y-m')]),
                                ])"
                                :empty="'Tidak ada '.mb_strtolower($title).' yang dicatat.'"
                            />
                        </section>
                    @endforeach
                    <x-link :href="route('opd.issues.index', ['period' => $period->format('Y-m')])">Lihat semua masalah & kebutuhan</x-link>
                </div>
            </x-card>
        </div>

        @include('shared.dashboards.trend', ['area' => 'opd'])
    </div>
</x-layouts.app>
