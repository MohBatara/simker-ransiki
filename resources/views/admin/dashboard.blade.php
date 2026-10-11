@use('App\Support\Format')
@use('App\Enums\OrganizationStatus')
@use('App\Support\Dashboards\DistrictStats')
@use('App\Support\Dashboards\ReportingStats')

@php
    $month = $period->format('Y-m');
    $verifiedUrl = route('admin.reports.index', ['status' => 'verified', 'period' => $month]);
    $attentionRows = [
        ['value' => $attention['without_opd'], 'label' => 'kelompok tanpa OPD pembina', 'href' => route('admin.organizations.index', ['opd' => 'none'])],
        ['value' => $attention['waiting'], 'label' => 'laporan menunggu verifikasi lebih dari '.DistrictStats::WAITING_DAYS.' hari', 'href' => route('admin.reports.index', ['status' => 'submitted', 'menunggu' => DistrictStats::WAITING_DAYS])],
        ['value' => $attention['active_without_account'], 'label' => 'kelompok aktif tanpa akun', 'href' => route('admin.organizations.index', ['status' => 'active', 'akun' => 'tidak-ada'])],
    ];
    $moneyRows = [
        'income_sales' => 'Pendapatan penjualan',
        'income_other' => 'Pendapatan lain',
        'costs' => 'Biaya',
        'profit' => 'Laba',
    ];
@endphp

<x-layouts.app title="Distrik Ransiki" eyebrow="Dasbor" :banner="$banner">
    <x-slot:actions>
        @include('shared.dashboards.period-picker')
    </x-slot:actions>

    <div class="space-y-5">
        <div class="grid gap-5 lg:grid-cols-3 lg:items-start">
            @include('shared.dashboards.overdue-stat', ['area' => 'admin'])

            <x-card title="Perlu perhatian" class="lg:col-span-2 lg:col-start-1 lg:row-start-1">
                <x-list>
                    @foreach ($attentionRows as $row)
                        <x-list.item :href="$row['href']">
                                <span @class(['w-12 shrink-0 text-right text-card-title font-semibold tabular-nums', 'text-ink' => $row['value'] > 0, 'text-ink-muted' => $row['value'] === 0])>{{ Format::number($row['value']) }}</span>
                                <span class="flex-1 text-ink">{{ ucfirst($row['label']) }}</span>
                        </x-list.item>
                    @endforeach
                </x-list>
            </x-card>
        </div>

        @include('shared.dashboards.progress', ['area' => 'admin'])

        <x-card title="Pelaporan per OPD" description="Kelompok dengan beberapa OPD dihitung di setiap OPD-nya; total distrik di atas dihitung unik." flush>
            <x-table bare class="max-md:p-4">
                <x-slot:head>
                    <x-table.heading>OPD</x-table.heading>
                    <x-table.heading numeric>Wajib lapor</x-table.heading>
                    <x-table.heading numeric>Terverifikasi</x-table.heading>
                    <x-table.heading numeric optional>Menunggu</x-table.heading>
                    <x-table.heading numeric optional>Revisi</x-table.heading>
                    <x-table.heading numeric>Belum diajukan</x-table.heading>
                    <x-table.heading><span class="sr-only">Progres</span></x-table.heading>
                </x-slot:head>
                @foreach ($perOpd as $row)
                    @php
                        $counts = $row['counts'];
                        $opdFilter = $row['opd'] ? $row['opd']->id : 'none';
                        $reportsUrl = fn (string $status) => route('admin.reports.index', ['opd' => $opdFilter, 'status' => $status, 'period' => $month]);
                        $cells = [
                            ['Terverifikasi', $counts['verified'], $reportsUrl('verified'), false],
                            ['Menunggu', $counts['submitted'], $reportsUrl('submitted'), true],
                            ['Revisi', $counts['revision'], $reportsUrl('revision'), true],
                            ['Belum diajukan', $counts['draft'] + $counts['missing'], route('admin.organizations.index', ['opd' => $opdFilter, 'laporan' => 'belum-diajukan', 'periode' => $month]), false],
                        ];
                    @endphp
                    @continue(! $row['opd'] && $counts['total'] === 0)
                    <x-table.row>
                        <x-table.cell primary>{{ $row['opd']?->short_name ?: ($row['opd']?->name ?? 'Tanpa OPD pembina') }}</x-table.cell>
                        <x-table.cell label="Wajib lapor" numeric>
                            <a href="{{ route('admin.organizations.index', ['opd' => $opdFilter]) }}" class="inline-flex min-h-11 items-center md:min-h-0 rounded-control hover:underline">{{ Format::number($counts['total']) }}</a>
                        </x-table.cell>
                        @foreach ($cells as [$label, $value, $href, $optional])
                            <x-table.cell :label="$label" numeric :optional="$optional">
                                <a href="{{ $href }}" class="inline-flex min-h-11 items-center md:min-h-0 rounded-control hover:underline">{{ Format::number($value) }}</a>
                            </x-table.cell>
                        @endforeach
                        <x-table.cell class="md:w-40 max-md:col-span-2 max-md:pt-2">
                            <x-stacked-bar size="sm" :legend="false" :label="'Pelaporan '.($row['opd']?->name ?? 'kelompok tanpa OPD')" :segments="ReportingStats::segments($counts, 'admin', $period)" />
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </x-card>

        @include('shared.dashboards.not-submitted', ['area' => 'admin'])

        <div class="grid gap-5 lg:grid-cols-2 lg:items-start">
            <x-card title="Kelompok">
                <div class="space-y-6">
                    <div>
                        <a href="{{ route('admin.organizations.index') }}" class="rounded-control hover:underline">
                            <span class="text-page-title font-semibold tabular-nums text-ink">{{ Format::number($organizations['total']) }}</span>
                            <span class="text-ink-soft">kelompok terdaftar</span>
                        </a>
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach (OrganizationStatus::cases() as $status)
                                <li>
                                    <a href="{{ route('admin.organizations.index', ['status' => $status->value]) }}" class="inline-flex min-h-11 items-center md:min-h-0 rounded-control">
                                        <x-badge :tone="$status->badgeColor()">{{ $status->label() }} · {{ $organizations['statuses'][$status->value] }}</x-badge>
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
            </x-card>

            <x-card :title="'Ekonomi '.Format::month($period)" description="Hanya dari laporan terverifikasi. Angka kosong tidak dihitung sebagai nol.">
                @if ($economy['reports'] === 0)
                    <p class="text-ink-soft">Belum ada laporan terverifikasi untuk bulan ini.</p>
                @else
                    <p class="text-ink-soft">
                        <a href="{{ $verifiedUrl }}" class="inline-flex min-h-11 items-center md:min-h-0 font-semibold tabular-nums text-ink hover:underline">{{ $economy['reports'] }} dari {{ $economy['obligated'] }}</a>
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
                                    <a href="{{ $verifiedUrl }}" class="inline-flex min-h-11 items-center md:min-h-0 font-semibold tabular-nums text-ink hover:underline">{{ Format::rupiah($total['sum']) }}</a>
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
                        <x-table bare class="md:-mx-4">
                            <x-slot:head>
                                <x-table.heading>Produk</x-table.heading>
                                <x-table.heading numeric>Produksi</x-table.heading>
                                <x-table.heading numeric>Kelompok</x-table.heading>
                            </x-slot:head>
                            @foreach ($production as $row)
                                <x-table.row>
                                    <x-table.cell primary>{{ $row['product'] }}</x-table.cell>
                                    <x-table.cell label="Produksi" numeric>
                                        @if ($row['quantity'] === null)
                                            <span class="text-ink-muted">Tidak diisi</span>
                                        @else
                                            {{ Format::quantity($row['quantity']) }} {{ $row['unit'] }}
                                            @if ($row['filled'] < $row['organizations'])
                                                <span class="block text-caption text-ink-muted">diisi {{ $row['filled'] }} dari {{ $row['organizations'] }} kelompok</span>
                                            @endif
                                        @endif
                                    </x-table.cell>
                                    <x-table.cell label="Kelompok" numeric>{{ $row['organizations'] }}</x-table.cell>
                                </x-table.row>
                            @endforeach
                        </x-table>
                    @endif
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
