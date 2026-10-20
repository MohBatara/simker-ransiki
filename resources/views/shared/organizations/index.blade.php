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
    ])->filter()->join(', ') ?: 'di '.Format::number($totals['villages']).' kampung';
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
        {{-- Kartu angka mengikuti filter aktif (ditukar oleh x-admin.toolbar saat filter berubah). --}}
        <div data-swap="kelompok-angka" class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-admin.stat compact icon="user-group" label="Total kelompok" :value="Format::number($totals['organizations'])" :hint="$totalHint" />
            <x-admin.stat compact icon="map-pin" label="Kampung" :value="Format::number($totals['villages'])" :hint="$scopeHint ?? 'tempat kelompok terdaftar'" :href="$isOpd ? null : route('admin.master.items', 'kampung')" />
            <x-admin.stat compact icon="squares-2x2" label="Bidang usaha" :value="Format::number($totals['sectors'])" :hint="$scopeHint ?? 'jenis bidang usaha'" :href="$isOpd ? null : route('admin.master.items', 'bidang-usaha')" />
            <x-admin.stat compact icon="users" label="Total anggota" :value="Format::number($totals['members'])" :hint="$membersHint" />
        </div>

        {{-- Klik baris membuka popup pratinjau tanpa pindah halaman (Ctrl/Cmd + klik nama: detail di tab baru). --}}
        <div x-data="organizationPanel(@js(route($area.'.organizations.summary', ['organization' => '__ID__'])))" x-on:click="select($event)">
            <x-admin.card flush>
                <x-admin.toolbar
                    :title="$isOpd ? 'Daftar kelompok binaan' : 'Daftar kelompok'"
                    :action="$url"
                    search-label="Cari nama kelompok"
                    :search="$filters['q'] ?? ''"
                    :filters="$toolbarFilters"
                    :keep="collect($filters)->only(['akun', 'laporan', 'periode'])->all()"
                    :chips="$chips"
                    :clear-url="$hasFilters ? $url : null"
                />

                <div data-swap="kelompok">
                    @if ($organizations->isEmpty())
                        @if ($hasFilters)
                            <x-admin.empty bare icon="magnifying-glass" title="Tidak ada kelompok yang cocok" description="Ubah kata pencarian atau hapus filter.">
                                <x-button variant="secondary" :href="$url">Hapus filter</x-button>
                            </x-admin.empty>
                        @elseif ($isOpd)
                            <x-admin.empty bare icon="user-group" title="Belum ada kelompok binaan" description="Kelompok dihubungkan ke OPD oleh Admin distrik. Hubungi Admin bila ada kelompok yang belum muncul." />
                        @else
                            <x-admin.empty bare icon="user-group" title="Belum ada kelompok" description="Tambah kelompok pertama agar bisa dipantau dan mulai melapor.">
                                <x-button icon="plus" :href="route('admin.organizations.create')">Tambah kelompok</x-button>
                            </x-admin.empty>
                        @endif
                    @else
                        <x-admin.table bare clickable id="daftar-kelompok" class="min-w-0">
                            <x-slot:head>
                                <x-admin.table.heading>Kelompok</x-admin.table.heading>
                                <x-admin.table.heading fit>Jenis</x-admin.table.heading>
                                <x-admin.table.heading fit>Kampung</x-admin.table.heading>
                                <x-admin.table.heading fit optional>Bidang usaha</x-admin.table.heading>
                                <x-admin.table.heading numeric fit optional>Anggota</x-admin.table.heading>
                                <x-admin.table.heading fit>Status</x-admin.table.heading>
                            </x-slot:head>

                            @foreach ($organizations as $organization)
                                <x-admin.table.row
                                    :href="route($area.'.organizations.show', $organization)"
                                    :edit="route($area.'.organizations.edit', $organization)"
                                    :for="$organization->name"
                                    view-label="Buka detail"
                                    :data-preview-id="$organization->id"
                                    :data-selected="$panel && $panel['organization']->is($organization) ? 'true' : null"
                                    class="data-[selected]:bg-brand-50"
                                >
                                    <x-admin.table.cell primary>
                                        <x-admin.identity :name="$organization->name" :subtitle="$organization->leader_name ? 'Ketua: '.$organization->leader_name : null" />
                                    </x-admin.table.cell>
                                    <x-admin.table.cell label="Jenis" fit><x-admin.chip icon="building-office-2">{{ $organization->institutionType->name }}</x-admin.chip></x-admin.table.cell>
                                    <x-admin.table.cell label="Kampung" fit>{{ Format::village($organization->village->name) }}</x-admin.table.cell>
                                    <x-admin.table.cell label="Bidang usaha" fit optional><x-admin.first-of :items="$organization->sectors->pluck('name')" /></x-admin.table.cell>
                                    <x-admin.table.cell label="Anggota" numeric fit optional>{{ Format::orEmpty(Format::number($organization->members_count)) }}</x-admin.table.cell>
                                    <x-admin.table.cell badge>
                                        <x-admin.badge-status :status="$organization->status" />
                                    </x-admin.table.cell>
                                </x-admin.table.row>
                            @endforeach

                            <x-slot:footer>
                                <x-admin.pagination :paginator="$organizations" noun="kelompok" />
                            </x-slot:footer>
                        </x-admin.table>
                    @endif
                </div>
            </x-admin.card>

            @include('shared.organizations._preview-modal')
        </div>
    </div>
</x-layouts.app>
