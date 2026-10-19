@use('App\Support\Format')
@use('App\Support\Dashboards\DistrictStats')
@use('App\Support\Dashboards\ReportingPeriod')

{{--
    Dasbor OPD, tab Ringkasan: pola yang sama dengan Admin (banner sapaan, Ekonomi + Masalah & kebutuhan, Perlu dikerjakan +
    Laporan terbaru, Produksi), lalu belum mengajukan. Semua angka dibatasi kelompok binaan (visibleTo).
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
        $profileQueue ? ['icon' => 'identification', 'title' => Format::number($profileQueue).' perubahan profil menunggu verifikasi', 'hint' => 'Kelompok binaan mengubah data penting profilnya setelah diverifikasi.', 'action' => 'Periksa '.Format::number($profileQueue).' perubahan', 'href' => route('opd.profile-verifications.index', ['jenis' => 'perubahan'])] : null,
        $profilesNew ? ['icon' => 'identification', 'title' => 'Periksa '.Format::number($profilesNew).' profil yang belum pernah diverifikasi', 'hint' => 'Kelompok yang didaftarkan sebelum verifikasi otomatis berlaku. Periksa lalu tandai terverifikasi.', 'action' => 'Periksa profil', 'href' => route('opd.profile-verifications.index', ['jenis' => 'baru'])] : null,
    ]));
    $submittedCount = $progress['verified'] + $progress['submitted'] + $progress['revision'];
    // Angka ekonomi periode terpilih = baris terakhir seri 6 bulan (kelompok binaan saja).
    $economy = $economyTrend->last();
@endphp

<x-layouts.app title="Dasbor" :description="'Ringkasan kelompok binaan '.$user->opd->name.'.'" section="dasbor" tab="ringkasan">
    <div class="space-y-6">
        <x-admin.greeting
            :name="$user->name"
            :subtitle="$user->opd->name"
            :highlight="$waitingCount
                ? ['value' => Format::number($waitingCount), 'label' => 'laporan menunggu verifikasi', 'icon' => 'clipboard-document-check', 'href' => route('opd.verifications.index')]
                : ['value' => Format::number($progress['verified']), 'label' => 'laporan terverifikasi '.Format::month($period), 'icon' => 'check-badge', 'href' => route('opd.reports.index', ['status' => 'verified', 'period' => $month])]"
            :sentence="Format::number($submittedCount).' dari '.Format::number($progress['total']).' kelompok binaan sudah mengajukan laporan '.Format::month($period).'.'"
            :action="$waitingCount ? ['label' => 'Mulai verifikasi', 'href' => route('opd.verifications.index')] : ['label' => 'Lihat semua laporan', 'href' => route('opd.reports.index')]"
        >
            @include('shared.dashboards.period-picker')
        </x-admin.greeting>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-stretch">
            @include('shared.dashboards.economy', ['area' => 'opd', 'class' => 'lg:col-span-8'])

            <x-admin.card :title="'Masalah & kebutuhan, '.Format::month($period)" description="Jumlah kelompok per kategori" stretch :more="route('opd.issues.index', ['period' => $month])" class="lg:col-span-4">
                <div class="space-y-5">
                    @foreach (['problem' => 'Masalah', 'support' => 'Kebutuhan'] as $kind => $title)
                        <section>
                            <h3 class="mb-2 text-caption font-semibold text-ink">{{ $title }}</h3>
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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-stretch">
            @include('shared.dashboards.tasks', ['area' => 'opd', 'class' => 'lg:col-span-5'])
            @include('shared.dashboards.latest-reports', ['area' => 'opd', 'class' => 'lg:col-span-7'])
        </div>

        @include('shared.dashboards.production', ['area' => 'opd'])

        @include('shared.dashboards.not-submitted', ['area' => 'opd'])
    </div>
</x-layouts.app>
