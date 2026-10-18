@use('App\Support\Format')
@use('App\Support\Dashboards\DistrictStats')
@use('App\Support\Dashboards\ReportingPeriod')

{{--
    Dasbor OPD, tab Ringkasan: pola yang sama dengan Admin (sapaan, Perlu dikerjakan, 4 kartu angka, 2 grafik ringkas),
    lalu belum mengajukan dan masalah & kebutuhan periode terpilih. Semua angka dibatasi kelompok binaan.
--}}
@php
    $month = $period->format('Y-m');
    $staleDays = config('simker.follow_up.stale_days');
    $waitingHint = collect([
        $waitingLong ? Format::number($waitingLong).' menunggu lebih dari '.DistrictStats::WAITING_DAYS.' hari' : null,
        $oldestWaiting ? 'paling lama: '.$oldestWaiting->organization->name.', '.Format::month($oldestWaiting->period).' ('.Format::waitingSince($oldestWaiting->last_submitted_at).')' : null,
    ])->filter()->join('; ');
    $tasks = array_values(array_filter([
        $waitingCount ? ['icon' => 'clipboard-document-check', 'title' => Format::number($waitingCount).' laporan menunggu verifikasi', 'hint' => $waitingHint ?: null, 'action' => 'Verifikasi '.Format::number($waitingCount).' laporan', 'href' => route('opd.verifications.index')] : null,
        $overdue['past_deadline'] && $overdue['total'] ? ['icon' => 'clock', 'title' => Format::number($overdue['total']).' kelompok binaan terlambat melapor '.Format::month($period), 'hint' => 'Tenggat '.Format::date($overdue['deadline']).', '.ReportingPeriod::deadlineDistance($overdue['deadline']).'. Salin pesan pengingat di daftar bawah.', 'action' => 'Ingatkan kelompok', 'href' => '#belum-melapor'] : null,
        $staleNeeds ? ['icon' => 'inbox-stack', 'title' => Format::number($staleNeeds).' kebutuhan belum ditindaklanjuti', 'hint' => 'Berstatus baru atau ditinjau lebih dari '.$staleDays.' hari.', 'action' => 'Tindak lanjuti', 'href' => route('opd.issues.index', ['kind' => 'support', 'lama' => $staleDays])] : null,
        $profileQueue ? ['icon' => 'identification', 'title' => Format::number($profileQueue).' profil menunggu verifikasi', 'hint' => 'Kelompok binaan mengubah data penting setelah profilnya diverifikasi.', 'action' => 'Periksa '.Format::number($profileQueue).' profil', 'href' => route('opd.profile-verifications.index')] : null,
    ]));
@endphp

<x-layouts.app title="Dasbor" :description="'Ringkasan kelompok binaan '.$user->opd->name.'.'" section="dasbor" tab="ringkasan" :header="false">
    <x-slot:lead>
        <x-admin.greeting :name="$user->name" :subtitle="$user->opd->name">
            @include('shared.dashboards.period-picker')
        </x-admin.greeting>
    </x-slot:lead>

    <div class="space-y-6">
        @include('shared.dashboards.tasks')

        @include('shared.dashboards.stats', ['area' => 'opd'])

        @include('shared.dashboards.charts', ['area' => 'opd'])

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
            @include('shared.dashboards.not-submitted', ['area' => 'opd'])

            <x-admin.card :title="'Masalah & kebutuhan, '.Format::month($period)" description="Jumlah kelompok per kategori, dari laporan yang sudah diajukan.">
                <x-slot:actions>
                    <x-link :href="route('opd.issues.index', ['period' => $month])">Lihat semua</x-link>
                </x-slot:actions>
                <div class="space-y-5">
                    @foreach (['problem' => 'Masalah', 'support' => 'Kebutuhan'] as $kind => $title)
                        <section>
                            <h3 class="mb-2 text-ui font-semibold text-ink">{{ $title }}</h3>
                            <x-bar-list
                                :items="$topIssues[$kind]->map(fn ($row) => [
                                    'label' => $row['label'],
                                    'value' => $row['organizations'],
                                    'href' => route('opd.issues.index', ['kind' => $kind, 'category' => $row['category'], 'period' => $month]),
                                ])"
                                :empty="'Tidak ada '.mb_strtolower($title).' yang dicatat.'"
                            />
                        </section>
                    @endforeach
                </div>
            </x-admin.card>
        </div>
    </div>
</x-layouts.app>
