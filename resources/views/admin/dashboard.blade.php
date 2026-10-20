@use('App\Support\Format')
@use('App\Enums\OrganizationStatus')
@use('App\Support\Dashboards\DistrictStats')
@use('App\Support\Dashboards\ReportingPeriod')
@use('App\Support\Dashboards\ReportingStats')

{{--
    Dasbor Admin, tab Ringkasan: banner sapaan (angka terpenting, sapaan, aksi, pemilih periode) → Ekonomi (8 kolom) +
    Kelompok (4 kolom) → Perlu dikerjakan (5) + Laporan terbaru (7) → Produksi per produk → pelaporan per OPD → belum
    mengajukan. HP: semua kartu bertumpuk.
--}}
@php
    $month = $period->format('Y-m');
    $staleDays = config('simker.follow_up.stale_days');
    $waitingHint = collect([
        $attention['waiting'] ? Format::number($attention['waiting']).' menunggu lebih dari '.DistrictStats::WAITING_DAYS.' hari' : null,
        $attention['admin_queue'] ? Format::number($attention['admin_queue']).' hanya bisa diverifikasi Admin' : null,
    ])->filter()->join(', ') ?: 'Yang paling lama menunggu ada di atas antrean.';
    $tasks = array_values(array_filter([
        $attention['waiting_total'] ? ['icon' => 'clipboard-document-check', 'title' => Format::number($attention['waiting_total']).' laporan menunggu verifikasi', 'hint' => $waitingHint, 'action' => 'Verifikasi '.Format::number($attention['waiting_total']).' laporan', 'href' => route('admin.verifications.index')] : null,
        $overdue['past_deadline'] && $overdue['total'] ? ['icon' => 'clock', 'title' => Format::number($overdue['total']).' kelompok terlambat melapor '.Format::month($period), 'hint' => 'Tenggat '.Format::date($overdue['deadline']).', '.ReportingPeriod::deadlineDistance($overdue['deadline']).'. Salin pesan pengingat di daftar bawah.', 'action' => 'Ingatkan kelompok', 'href' => '#belum-melapor'] : null,
        $staleNeeds ? ['icon' => 'inbox-stack', 'title' => Format::number($staleNeeds).' kebutuhan belum ditindaklanjuti', 'hint' => 'Berstatus baru atau ditinjau lebih dari '.$staleDays.' hari.', 'action' => 'Tindak lanjuti', 'href' => route('admin.issues.index', ['kind' => 'support', 'lama' => $staleDays])] : null,
        $profileQueue ? ['icon' => 'identification', 'title' => Format::number($profileQueue).' perubahan profil menunggu verifikasi', 'hint' => 'Kelompok mengubah data penting profilnya setelah diverifikasi.', 'action' => 'Periksa '.Format::number($profileQueue).' perubahan', 'href' => route('admin.profile-verifications.index', ['jenis' => 'perubahan'])] : null,
        $profilesNew ? ['icon' => 'identification', 'title' => 'Periksa '.Format::number($profilesNew).' profil yang belum pernah diverifikasi', 'hint' => 'Kelompok yang didaftarkan sebelum verifikasi otomatis berlaku. Periksa lalu tandai terverifikasi.', 'action' => 'Periksa profil', 'href' => route('admin.profile-verifications.index', ['jenis' => 'baru'])] : null,
        $attention['active_without_account'] ? ['icon' => 'user-plus', 'title' => Format::number($attention['active_without_account']).' kelompok aktif belum punya akun', 'hint' => 'Tanpa akun, kelompok belum bisa mengisi laporan sendiri.', 'action' => 'Buat akun '.Format::number($attention['active_without_account']).' kelompok', 'href' => route('admin.organizations.index', ['status' => 'active', 'akun' => 'tidak-ada'])] : null,
        $attention['without_opd'] ? ['icon' => 'building-office-2', 'title' => Format::number($attention['without_opd']).' kelompok belum punya OPD pembina', 'hint' => 'Laporannya hanya bisa diverifikasi Admin.', 'action' => 'Tetapkan OPD', 'href' => route('admin.organizations.index', ['opd' => 'none'])] : null,
    ]));
    $submittedCount = $progress['verified'] + $progress['submitted'] + $progress['revision'];
    $waitingTotal = $attention['waiting_total'];
@endphp

<x-layouts.app title="Dasbor" description="Ringkasan pelaporan dan ekonomi kelompok di Distrik Ransiki." section="dasbor" tab="ringkasan">
    <div class="space-y-6">
        <x-admin.greeting
            :name="auth()->user()->name"
            subtitle="Distrik Ransiki"
            :highlight="$waitingTotal
                ? ['value' => Format::number($waitingTotal), 'label' => 'laporan menunggu verifikasi', 'icon' => 'clipboard-document-check', 'href' => route('admin.verifications.index')]
                : ['value' => Format::number($progress['verified']), 'label' => 'laporan terverifikasi '.Format::month($period), 'icon' => 'check-badge', 'href' => route('admin.reports.index', ['status' => 'verified', 'period' => $month])]"
            :sentence="Format::number($submittedCount).' dari '.Format::number($progress['total']).' kelompok wajib lapor sudah mengajukan laporan '.Format::month($period).'.'"
            :action="$waitingTotal ? ['label' => 'Mulai verifikasi', 'href' => route('admin.verifications.index')] : ['label' => 'Lihat semua laporan', 'href' => route('admin.reports.index')]"
        >
            @include('shared.dashboards.period-picker')
        </x-admin.greeting>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-stretch">
            @include('shared.dashboards.economy', ['area' => 'admin', 'class' => 'lg:col-span-8'])
            @include('shared.dashboards.organizations', ['class' => 'lg:col-span-4'])
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-stretch">
            @include('shared.dashboards.tasks', ['area' => 'admin', 'class' => 'lg:col-span-5'])
            @include('shared.dashboards.latest-reports', ['area' => 'admin', 'class' => 'lg:col-span-7'])
        </div>

        {{-- Produksi per produk tidak selebar penuh: nama dan angka berdekatan. --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-stretch">
            @include('shared.dashboards.production', ['area' => 'admin', 'class' => 'lg:col-span-7'])
            @include('shared.dashboards.not-submitted', ['area' => 'admin', 'class' => 'lg:col-span-5'])
        </div>

        @include('admin.dashboard._opd-reporting')
    </div>
</x-layouts.app>
