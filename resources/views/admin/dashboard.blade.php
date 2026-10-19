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
        $profileQueue ? ['icon' => 'identification', 'title' => Format::number($profileQueue).' profil menunggu verifikasi', 'hint' => 'Kelompok mengubah data penting setelah profilnya diverifikasi.', 'action' => 'Periksa '.Format::number($profileQueue).' profil', 'href' => route('admin.profile-verifications.index')] : null,
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

        @include('shared.dashboards.production', ['area' => 'admin'])

        <x-admin.card title="Pelaporan per OPD" :description="'Periode '.Format::month($period).'. Kelompok dengan beberapa OPD dihitung di setiap OPD-nya.'" flush>
            <x-admin.table bare>
                <x-slot:head>
                    <x-admin.table.heading>OPD</x-admin.table.heading>
                    <x-admin.table.heading numeric>Wajib lapor</x-admin.table.heading>
                    <x-admin.table.heading numeric>Terverifikasi</x-admin.table.heading>
                    <x-admin.table.heading numeric optional wrap>Menunggu verifikasi</x-admin.table.heading>
                    <x-admin.table.heading numeric optional wrap>Perlu revisi</x-admin.table.heading>
                    <x-admin.table.heading numeric>Belum diajukan</x-admin.table.heading>
                    <x-admin.table.heading><span class="sr-only">Progres</span></x-admin.table.heading>
                </x-slot:head>
                @foreach ($perOpd as $row)
                    @php
                        $counts = $row['counts'];
                        $opdFilter = $row['opd'] ? $row['opd']->id : 'none';
                        $reportsUrl = fn (string $status) => route('admin.reports.index', ['opd' => $opdFilter, 'status' => $status, 'period' => $month]);
                        $cells = [
                            ['Terverifikasi', $counts['verified'], $reportsUrl('verified'), false],
                            ['Menunggu verifikasi', $counts['submitted'], $reportsUrl('submitted'), true],
                            ['Perlu revisi', $counts['revision'], $reportsUrl('revision'), true],
                            ['Belum diajukan', $counts['draft'] + $counts['missing'], route('admin.organizations.index', ['opd' => $opdFilter, 'laporan' => 'belum-diajukan', 'periode' => $month]), false],
                        ];
                    @endphp
                    @continue(! $row['opd'] && $counts['total'] === 0)
                    <x-admin.table.row>
                        <x-admin.table.cell primary>{{ $row['opd']?->short_name ?: ($row['opd']?->name ?? 'Tanpa OPD pembina') }}</x-admin.table.cell>
                        <x-admin.table.cell label="Wajib lapor" numeric>
                            <a href="{{ route('admin.organizations.index', ['opd' => $opdFilter]) }}" class="inline-flex min-h-11 items-center rounded-control hover:underline md:min-h-0">{{ Format::number($counts['total']) }}</a>
                        </x-admin.table.cell>
                        @foreach ($cells as [$label, $value, $href, $optional])
                            <x-admin.table.cell :label="$label" numeric :optional="$optional">
                                <a href="{{ $href }}" class="inline-flex min-h-11 items-center rounded-control hover:underline md:min-h-0">{{ Format::number($value) }}</a>
                            </x-admin.table.cell>
                        @endforeach
                        <x-admin.table.cell class="md:w-40 max-md:col-span-2 max-md:pt-2">
                            <x-stacked-bar size="sm" :legend="false" :label="'Pelaporan '.($row['opd']?->name ?? 'kelompok tanpa OPD')" :segments="ReportingStats::segments($counts, 'admin', $period)" />
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @endforeach
            </x-admin.table>
        </x-admin.card>

        @include('shared.dashboards.not-submitted', ['area' => 'admin'])
    </div>
</x-layouts.app>
