@use('App\Support\Format')

@php
    $isOpd = $area === 'opd';
    $url = route($area.'.organizations.index');
    $validPeriod = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($filters['periode'] ?? ''));
    $month = $validPeriod ? Format::month($filters['periode'].'-01') : null;

    // Filter dari tautan dasbor (akun, laporan) tidak punya pilihan di bilah alat; tampil sebagai pil.
    $chips = \App\Support\FilterChips::from($filters, [
        'q' => [null, null],
        'type' => ['Jenis', $options['types']],
        'village' => ['Kampung', $options['villages']],
        'sector' => ['Bidang', $options['sectors']],
        'opd' => ['OPD', ['none' => 'Belum ada OPD'] + $options['opds']],
        'status' => ['Status', $options['statuses']],
        'akun' => [null, ['tidak-ada' => 'Belum punya akun']],
        'laporan' => [null, $month ? ['belum-dibuat' => "Belum membuat laporan {$month}", 'belum-diajukan' => "Belum mengajukan laporan {$month}", 'terlambat' => "Terlambat melapor {$month}"] : [], ['periode']],
    ]);
    $hasFilters = $chips !== [];
    $toolbarFilters = array_values(array_filter([
        ['name' => 'village', 'label' => 'Kampung', 'options' => $options['villages'], 'selected' => $filters['village'] ?? null, 'placeholder' => 'Semua kampung'],
        ['name' => 'type', 'label' => 'Jenis', 'options' => $options['types'], 'selected' => $filters['type'] ?? null, 'placeholder' => 'Semua jenis'],
        ['name' => 'status', 'label' => 'Status', 'options' => $options['statuses'], 'selected' => $filters['status'] ?? null, 'placeholder' => 'Semua status'],
        ['name' => 'sector', 'label' => 'Bidang usaha', 'options' => $options['sectors'], 'selected' => $filters['sector'] ?? null, 'placeholder' => 'Semua bidang'],
        $isOpd ? null : ['name' => 'opd', 'label' => 'OPD pembina', 'options' => ['none' => 'Belum ada OPD'] + $options['opds'], 'selected' => $filters['opd'] ?? null, 'placeholder' => 'Semua OPD'],
    ]));
    $scopeHint = $hasFilters ? 'sesuai filter' : null;
    // Info tidak aktif/ditutup sebagai keterangan kartu Total kelompok (tidak diulang di baris ringkasan).
    $totalHint = collect([
        $statusCounts['inactive'] ? $statusCounts['inactive'].' tidak aktif' : null,
        $statusCounts['closed'] ? $statusCounts['closed'].' ditutup' : null,
        $scopeHint,
    ])->filter()->join(', ') ?: null;
    $membersHint = $totals['members'] === null
        ? 'belum ada yang mengisi'
        : 'diisi '.$totals['members_filled'].' dari '.$totals['organizations'].' kelompok';
@endphp

{{-- Dipakai Admin (Kelompok › Semua kelompok) dan OPD (Kelompok binaan › Daftar); isi sudah disaring scope visibleTo. --}}
<x-layouts.app
    :title="$isOpd ? 'Kelompok binaan' : 'Semua kelompok'"
    :description="$isOpd ? 'Kelompok yang dibina OPD Anda.' : 'Kelompok dan kelembagaan ekonomi di Distrik Ransiki.'"
    section="kelompok"
    :tab="$isOpd ? 'daftar' : 'semua'"
>
    <x-slot:secondary>
        {{-- Mengikuti filter yang sedang aktif (ditukar saat filter berubah). --}}
        <x-button variant="secondary" icon="arrow-down-tray" :href="route($area.'.organizations.export', array_filter($filters, 'filled'))" data-swap="unduh-kelompok">Unduh Excel</x-button>
        @can('create', App\Models\Organization::class)
            <x-button variant="secondary" icon="arrow-up-tray" :href="route('admin.organizations.import.create')">Impor</x-button>
        @endcan
    </x-slot:secondary>
    @can('create', App\Models\Organization::class)
        <x-slot:actions>
            <x-button icon="plus" :href="route('admin.organizations.create')">Tambah kelompok</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        <x-admin.toolbar
            :action="$url"
            search-label="Cari nama kelompok"
            :search="$filters['q'] ?? ''"
            :filters="$toolbarFilters"
            :keep="collect($filters)->only(['akun', 'laporan', 'periode'])->all()"
            :chips="$chips"
            :clear-url="$hasFilters ? $url : null"
        />

        {{-- Ditukar oleh x-admin.toolbar saat filter berubah: kartu angka mengikuti filter, lalu tabel. --}}
        <div data-swap="kelompok" class="space-y-6">
            <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <x-admin.stat compact icon="user-group" label="Total kelompok" :value="Format::number($totals['organizations'])" :hint="$totalHint" />
                <x-admin.stat compact icon="map-pin" label="Kampung" :value="Format::number($totals['villages'])" :hint="$scopeHint" :href="$isOpd ? null : route('admin.master.items', 'kampung')" />
                <x-admin.stat compact icon="squares-2x2" label="Bidang usaha" :value="Format::number($totals['sectors'])" :hint="$scopeHint" :href="$isOpd ? null : route('admin.master.items', 'bidang-usaha')" />
                <x-admin.stat compact icon="users" label="Total anggota" :value="Format::number($totals['members'])" :hint="$membersHint" />
            </div>

            @if ($organizations->isEmpty())
                @if ($hasFilters)
                    <x-admin.empty icon="magnifying-glass" title="Tidak ada kelompok yang cocok" description="Ubah kata pencarian atau hapus filter.">
                        <x-button variant="secondary" :href="$url">Hapus filter</x-button>
                    </x-admin.empty>
                @elseif ($isOpd)
                    <x-admin.empty icon="user-group" title="Belum ada kelompok binaan" description="Kelompok dihubungkan ke OPD oleh Admin distrik. Hubungi Admin bila ada kelompok yang belum muncul." />
                @else
                    <x-admin.empty icon="user-group" title="Belum ada kelompok" description="Tambah kelompok pertama agar bisa dipantau dan mulai melapor.">
                        <x-button icon="plus" :href="route('admin.organizations.create')">Tambah kelompok</x-button>
                    </x-admin.empty>
                @endif
            @else
                {{-- Desktop (≥ 1280px): klik baris memuat panel detail di kanan; layar lebih kecil membuka halaman detail. --}}
                <div x-data="organizationPanel" class="xl:grid xl:grid-cols-[minmax(0,1fr)_25rem] xl:items-start xl:gap-6">
                    <x-admin.table clickable id="daftar-kelompok" x-on:click="select($event)" class="min-w-0">
                        <x-slot:head>
                            <x-admin.table.heading>Kelompok</x-admin.table.heading>
                            <x-admin.table.heading>Kampung</x-admin.table.heading>
                            {{-- ≥ 1280px panel detail selalu tampil: kolom Bidang usaha dan Anggota (ada di panel) disembunyikan. --}}
                            <x-admin.table.heading optional class="xl:hidden">Bidang usaha</x-admin.table.heading>
                            <x-admin.table.heading numeric optional class="xl:hidden">Anggota</x-admin.table.heading>
                            <x-admin.table.heading>Status</x-admin.table.heading>
                        </x-slot:head>

                        @foreach ($organizations as $organization)
                            <x-admin.table.row
                                :href="route($area.'.organizations.show', $organization)"
                                :data-panel-url="route($area.'.organizations.summary', $organization)"
                                :data-id="$organization->id"
                                :data-selected="$panel && $panel['organization']->is($organization) ? 'true' : null"
                                class="xl:data-[selected]:bg-brand-tint"
                            >
                                <x-admin.table.cell primary class="xl:w-full xl:max-w-0">
                                    <span class="flex items-center gap-3">
                                        <x-avatar :name="$organization->name" />
                                        <span class="min-w-0">
                                            <span class="block xl:truncate" title="{{ $organization->name }}">{{ $organization->name }}</span>
                                            <span class="block text-caption font-normal text-ink-muted xl:truncate">{{ $organization->institutionType->name }}</span>
                                        </span>
                                    </span>
                                </x-admin.table.cell>
                                <x-admin.table.cell label="Kampung" class="xl:whitespace-nowrap">{{ $organization->village->name }}</x-admin.table.cell>
                                <x-admin.table.cell label="Bidang usaha" optional class="xl:hidden">{{ $organization->sectors->pluck('name')->join(', ') }}</x-admin.table.cell>
                                <x-admin.table.cell label="Anggota" numeric optional class="xl:hidden">{{ Format::number($organization->members_count) }}</x-admin.table.cell>
                                <x-admin.table.cell badge>
                                    <x-admin.badge-status :status="$organization->status" />
                                </x-admin.table.cell>
                            </x-admin.table.row>
                        @endforeach

                        <x-slot:footer>
                            <x-admin.pagination :paginator="$organizations" noun="kelompok" />
                        </x-slot:footer>
                    </x-admin.table>

                    <aside class="hidden xl:sticky xl:top-20 xl:block" aria-live="polite" x-bind:aria-busy="loading">
                        <div x-ref="panel" x-bind:class="loading && 'opacity-60'" class="transition-opacity">
                            @if ($panel)
                                @include('shared.organizations._panel')
                            @endif
                        </div>
                        <template x-ref="empty">
                            <div class="rounded-tile border border-dashed border-line-strong bg-paper px-5 py-10 text-center">
                                <p class="font-semibold text-ink">Pilih kelompok untuk melihat ringkasannya.</p>
                                <p class="mt-1 text-ink-soft">Klik salah satu baris di daftar.</p>
                            </div>
                        </template>
                    </aside>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
