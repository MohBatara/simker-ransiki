@use('App\Support\Format')
@use('App\Enums\OrganizationStatus')
@use('App\Support\Dashboards\DistrictStats')
@use('App\Support\Dashboards\ReportingPeriod')
@use('App\Support\Dashboards\ReportingStats')

{{--
    Dasbor Admin, tab Ringkasan: sapaan + pemilih periode → Perlu dikerjakan (tugas berprioritas dengan tombol aksi)
    → 4 kartu angka → 2 grafik ringkas → pelaporan per OPD → belum mengajukan → kelompok dan ekonomi.
--}}
@php
    $month = $period->format('Y-m');
    $verifiedUrl = route('admin.reports.index', ['status' => 'verified', 'period' => $month]);
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
    $moneyRows = [
        'income_sales' => 'Pendapatan penjualan',
        'income_other' => 'Pendapatan lain',
        'costs' => 'Biaya',
        'profit' => 'Laba',
    ];
@endphp

<x-layouts.app title="Dasbor" description="Ringkasan pelaporan dan kelompok di Distrik Ransiki." section="dasbor" tab="ringkasan" :header="false">
    <x-slot:lead>
        <x-admin.greeting :name="auth()->user()->name" subtitle="Distrik Ransiki">
            @include('shared.dashboards.period-picker')
        </x-admin.greeting>
    </x-slot:lead>

    <div class="space-y-6">
        @include('shared.dashboards.tasks')

        @include('shared.dashboards.stats', ['area' => 'admin'])

        @include('shared.dashboards.charts', ['area' => 'admin'])

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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
            <x-admin.card title="Kelompok">
                <x-slot:actions>
                    <x-link :href="route('admin.organizations.index')">Lihat semua</x-link>
                </x-slot:actions>
                <div class="space-y-6">
                    <div>
                        <p>
                            <span class="text-display-sm font-semibold tabular-nums text-ink">{{ Format::number($organizations['total']) }}</span>
                            <span class="text-ink-soft">kelompok terdaftar</span>
                        </p>
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach (OrganizationStatus::cases() as $status)
                                <li>
                                    <a href="{{ route('admin.organizations.index', ['status' => $status->value]) }}" class="inline-flex min-h-11 items-center rounded-full md:min-h-0">
                                        <x-admin.badge-status :status="$status" :label="$status->label().' · '.$organizations['statuses'][$status->value]" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <section>
                        <h3 class="mb-2 text-ui font-semibold text-ink">Jenis kelembagaan</h3>
                        <x-bar-list :items="$organizations['types']->map(fn ($row) => [...$row, 'href' => route('admin.organizations.index', ['type' => $row['id']])])" />
                    </section>

                    <section>
                        <h3 class="text-ui font-semibold text-ink">Bidang usaha</h3>
                        <p class="mb-2 text-caption text-ink-muted">Satu kelompok bisa punya beberapa bidang usaha, jadi jumlahnya bisa melebihi total kelompok.</p>
                        <x-bar-list :items="$organizations['sectors']->map(fn ($row) => [...$row, 'href' => route('admin.organizations.index', ['sector' => $row['id']])])" />
                    </section>
                </div>
            </x-admin.card>

            <x-admin.card :title="'Ekonomi '.Format::month($period)" description="Hanya dari laporan terverifikasi. Angka kosong tidak dihitung sebagai nol.">
                @if ($economy['reports'] === 0)
                    <p class="text-ink-soft">Belum ada laporan terverifikasi untuk bulan ini.</p>
                @else
                    <p class="text-ink-soft">
                        <a href="{{ $verifiedUrl }}" class="inline-flex min-h-11 items-center font-semibold tabular-nums text-ink hover:underline md:min-h-0">{{ $economy['reports'] }} dari {{ $economy['obligated'] }}</a>
                        kelompok wajib lapor sudah terverifikasi.
                    </p>

                    <dl class="mt-4 divide-y divide-line">
                        @foreach ($moneyRows as $field => $label)
                            @php $total = $economy['totals'][$field]; @endphp
                            <div class="flex items-baseline justify-between gap-4 py-3 first:pt-0">
                                <dt>
                                    <span class="text-ink">{{ $label }}</span>
                                    <span class="block text-caption text-ink-muted">
                                        @if ($total['filled'] === 0)
                                            Belum ada yang mengisi
                                        @else
                                            diisi {{ $total['filled'] }} dari {{ $economy['reports'] }} laporan
                                        @endif
                                    </span>
                                </dt>
                                <dd class="text-right">
                                    <a href="{{ $verifiedUrl }}" class="inline-flex min-h-11 items-center font-semibold tabular-nums text-ink hover:underline md:min-h-0">{{ Format::rupiah($total['sum']) }}</a>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-1 text-caption text-ink-muted">Laba hanya dari laporan yang mengisi penjualan, pendapatan lain, dan biaya.</p>

                    <h3 class="mt-6 text-ui font-semibold text-ink">Produksi per produk</h3>
                    <p class="mb-2 text-caption text-ink-muted">10 produk dengan kelompok terbanyak. Satuan berbeda tidak dijumlahkan.</p>
                    @if ($production->isEmpty())
                        <p class="text-ink-soft">Belum ada produksi yang dilaporkan.</p>
                    @else
                        <x-admin.table bare label="Produksi per produk">
                            <x-slot:head>
                                <x-admin.table.heading>Produk</x-admin.table.heading>
                                <x-admin.table.heading numeric>Produksi</x-admin.table.heading>
                                <x-admin.table.heading numeric>Kelompok</x-admin.table.heading>
                            </x-slot:head>
                            @foreach ($production as $row)
                                <x-admin.table.row>
                                    <x-admin.table.cell primary>{{ $row['product'] }}</x-admin.table.cell>
                                    <x-admin.table.cell label="Produksi" numeric>
                                        @if ($row['quantity'] === null)
                                            <span class="text-ink-muted">Tidak diisi</span>
                                        @else
                                            {{ Format::quantity($row['quantity']) }} {{ $row['unit'] }}
                                            @if ($row['filled'] < $row['organizations'])
                                                <span class="block text-caption text-ink-muted">diisi {{ $row['filled'] }} dari {{ $row['organizations'] }} kelompok</span>
                                            @endif
                                        @endif
                                    </x-admin.table.cell>
                                    <x-admin.table.cell label="Kelompok" numeric>{{ $row['organizations'] }}</x-admin.table.cell>
                                </x-admin.table.row>
                            @endforeach
                        </x-admin.table>
                    @endif
                @endif
            </x-admin.card>
        </div>
    </div>
</x-layouts.app>
