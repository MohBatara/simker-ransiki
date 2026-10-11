@php
    $isOpd = $area === 'opd';
    $url = route($area.'.organizations.index');
    $selects = $isOpd ? ['type', 'village', 'sector', 'status'] : ['type', 'village', 'sector', 'opd', 'status'];
    $active = collect($filters)->only($selects)->filter(fn ($value) => filled($value))->count();
    $validPeriod = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($filters['periode'] ?? ''));
    $month = $validPeriod ? \App\Support\Format::month($filters['periode'].'-01') : null;

    // Filter dari tautan dasbor (akun, laporan) tidak punya pilihan di form; tampil sebagai chip.
    $chips = \App\Support\FilterChips::from($filters, [
        'q' => [null, null],
        'type' => ['Jenis', $options['types']],
        'village' => ['Kampung', $options['villages']],
        'sector' => ['Bidang', $options['sectors']],
        'opd' => ['OPD', ['none' => 'Belum ada OPD'] + $options['opds']],
        'status' => ['Status', $options['statuses']],
        'akun' => [null, ['tidak-ada' => 'Belum punya akun']],
        'laporan' => [null, $month ? ['belum-dibuat' => "Belum membuat laporan {$month}", 'belum-diajukan' => "Belum mengajukan laporan {$month}"] : [], ['periode']],
    ]);
    $hasFilters = $chips !== [];
    $summary = collect([
        $organizations->total().' kelompok',
        $statusCounts['inactive'] ? $statusCounts['inactive'].' tidak aktif' : null,
        $statusCounts['closed'] ? $statusCounts['closed'].' ditutup' : null,
    ])->filter()->join(' · ');
@endphp

{{-- Dipakai Admin ("Semua Kelompok") dan OPD ("Kelompok Binaan"); isi sudah disaring scope visibleTo. --}}
<x-layouts.app
    :title="$isOpd ? 'Kelompok Binaan' : 'Semua Kelompok'"
    :description="$isOpd ? 'Kelompok yang dibina OPD Anda.' : 'Kelompok dan kelembagaan ekonomi di Distrik Ransiki.'"
>
    @can('create', App\Models\Organization::class)
        <x-slot:actions>
            <x-button icon="plus" :href="route('admin.organizations.create')">Tambah kelompok</x-button>
        </x-slot:actions>
    @endcan

    @php
        $filtered = $hasFilters;
        $scopeHint = $filtered ? 'sesuai filter' : null;
        $membersHint = $totals['members'] === null
            ? 'belum ada yang mengisi'
            : 'diisi '.$totals['members_filled'].' dari '.$totals['organizations'].' kelompok';
    @endphp

    <div class="space-y-5">
        {{-- Kartu ringkasan: mengikuti filter yang aktif agar sama dengan isi tabel. --}}
        @if ($filtered)
            <p class="-mb-2 text-caption text-ink-muted md:hidden">Ringkasan sesuai filter.</p>
        @endif
        <div class="grid grid-cols-2 gap-3 md:gap-4 xl:grid-cols-4">
            <x-stat icon="user-group" label="Total kelompok" :value="App\Support\Format::number($totals['organizations'])" :hint-md="$scopeHint" href="#daftar-kelompok" />
            <x-stat icon="map-pin" label="Kampung" :value="App\Support\Format::number($totals['villages'])" :hint-md="$scopeHint" :href="$isOpd ? null : route('admin.master.items', 'kampung')" />
            <x-stat icon="squares-2x2" label="Bidang usaha" :value="App\Support\Format::number($totals['sectors'])" :hint-md="$scopeHint" :href="$isOpd ? null : route('admin.master.items', 'bidang-usaha')" />
            <x-stat icon="users" label="Total anggota" :value="App\Support\Format::number($totals['members'])" :hint="$membersHint" :hint-md="$scopeHint" />
        </div>

        {{-- Kartu filter: dipilih dulu, lalu Terapkan (hemat muat ulang di koneksi lambat). --}}
        {{-- HP: kotak cari sebaris dengan tombol "Filter (n)", pilihan lain di panel. Desktop: dua baris seimbang. --}}
        <x-card flush x-data="{ open: false }" class="max-md:border-0 max-md:bg-transparent">
            <form id="filter-kelompok" method="GET" action="{{ $url }}" role="search" class="grid gap-4 md:grid-cols-4 md:items-end md:p-5">
                @foreach (collect($filters)->only(['akun', 'laporan', 'periode']) as $name => $value)
                    @if (filled($value))
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach
                <div class="flex items-end gap-2 md:col-span-2">
                    <x-field label="Cari kelompok" for="q" label-class="max-md:sr-only" class="min-w-0 flex-1">
                        <x-search-input :value="$filters['q'] ?? ''" label="Nama kelompok" :with-label="false" />
                    </x-field>
                    <x-button variant="secondary" icon="adjustments-horizontal" class="shrink-0 md:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="panel-filter-kelompok">
                        Filter{{ $active ? " ({$active})" : '' }}
                    </x-button>
                </div>
                <div id="panel-filter-kelompok" x-bind:class="{ 'max-md:hidden': ! open }" class="grid gap-4 max-md:hidden sm:grid-cols-2 md:contents">
                    <x-field label="Jenis" for="type">
                        <x-select name="type" :options="$options['types']" :selected="$filters['type'] ?? null" placeholder="Semua jenis" />
                    </x-field>
                    <x-field label="Kampung" for="village">
                        <x-select name="village" :options="$options['villages']" :selected="$filters['village'] ?? null" placeholder="Semua kampung" />
                    </x-field>
                    <x-field label="Bidang usaha" for="sector">
                        <x-select name="sector" :options="$options['sectors']" :selected="$filters['sector'] ?? null" placeholder="Semua bidang" />
                    </x-field>
                    @unless ($isOpd)
                        <x-field label="OPD pembina" for="opd">
                            <x-select name="opd" :options="['none' => 'Belum ada OPD'] + $options['opds']" :selected="$filters['opd'] ?? null" placeholder="Semua OPD" />
                        </x-field>
                    @endunless
                    <x-field label="Status" for="status">
                        <x-select name="status" :options="$options['statuses']" :selected="$filters['status'] ?? null" placeholder="Semua status" />
                    </x-field>
                    <div class="flex gap-2 max-sm:*:flex-1 sm:col-span-2 md:col-span-1 md:col-start-4 md:justify-end">
                        <x-button type="submit">Terapkan</x-button>
                        <x-button variant="secondary" icon="arrow-path" :href="$url">Reset</x-button>
                    </div>
                </div>
            </form>
        </x-card>

        <x-filter-summary :summary="$summary" :chips="$chips" :clear-url="$url" />

        @if ($organizations->isEmpty())
            @if ($hasFilters)
                <x-empty-state title="Tidak ada kelompok yang cocok" description="Ubah kata pencarian atau hapus filter.">
                    <x-button variant="secondary" :href="$url">Hapus filter</x-button>
                </x-empty-state>
            @else
                @if ($isOpd)
                    <x-empty-state title="Belum ada kelompok binaan" description="Kelompok dihubungkan ke OPD oleh Admin distrik. Hubungi Admin bila ada kelompok yang belum muncul." />
                @else
                    <x-empty-state title="Belum ada kelompok" description="Tambah kelompok pertama agar bisa dipantau dan mulai melapor.">
                        <x-button icon="plus" :href="route('admin.organizations.create')">Tambah kelompok</x-button>
                    </x-empty-state>
                @endif
            @endif
        @else
            {{-- Desktop (≥ 1280px): klik baris memuat panel detail di kanan; layar lebih kecil membuka halaman detail. --}}
            <div x-data="organizationPanel" class="xl:grid xl:grid-cols-[minmax(0,1fr)_25rem] xl:items-start xl:gap-5">
                <x-table clickable id="daftar-kelompok" x-on:click="select($event)" class="min-w-0">
                    <x-slot:head>
                        <x-table.heading>Kelompok</x-table.heading>
                        <x-table.heading>Kampung</x-table.heading>
                        <x-table.heading optional>Bidang usaha</x-table.heading>
                        <x-table.heading numeric optional>Anggota</x-table.heading>
                        <x-table.heading><span class="sr-only">Status</span></x-table.heading>
                    </x-slot:head>

                    @foreach ($organizations as $organization)
                        <x-table.row
                            :href="route($area.'.organizations.show', $organization)"
                            :data-panel-url="route($area.'.organizations.summary', $organization)"
                            :data-id="$organization->id"
                            :data-selected="$panel && $panel['organization']->is($organization) ? 'true' : null"
                            class="xl:data-[selected]:bg-brand-tint"
                        >
                            <x-table.cell primary>
                                <span class="flex items-center gap-3">
                                    <x-avatar :name="$organization->name" />
                                    <span class="min-w-0">
                                        <span class="block">{{ $organization->name }}</span>
                                        <span class="block text-caption font-normal text-ink-muted">{{ $organization->institutionType->name }}</span>
                                    </span>
                                </span>
                            </x-table.cell>
                            <x-table.cell label="Kampung">{{ $organization->village->name }}</x-table.cell>
                            <x-table.cell label="Bidang usaha" optional>{{ $organization->sectors->pluck('name')->join(', ') }}</x-table.cell>
                            <x-table.cell label="Anggota" numeric optional>{{ App\Support\Format::number($organization->members_count) }}</x-table.cell>
                            {{-- Status hanya ditandai bila bukan Aktif. --}}
                            <x-table.cell badge>
                                @if ($organization->status !== App\Enums\OrganizationStatus::Active)
                                    <x-badge :tone="$organization->status->badgeColor()">{{ $organization->status->label() }}</x-badge>
                                @endif
                            </x-table.cell>
                        </x-table.row>
                    @endforeach

                    <x-slot:footer>
                        <div class="space-y-3">
                            <p class="text-caption text-ink-muted tabular-nums">
                                Menampilkan {{ $organizations->firstItem() }}–{{ $organizations->lastItem() }} dari {{ $organizations->total() }} kelompok
                            </p>
                            {{ $organizations->links() }}
                        </div>
                    </x-slot:footer>
                </x-table>

                <aside class="hidden xl:sticky xl:top-20 xl:block" aria-live="polite" x-bind:aria-busy="loading">
                    <div x-ref="panel" x-bind:class="loading && 'opacity-60'" class="transition-opacity">
                        @if ($panel)
                            @include('shared.organizations._panel')
                        @endif
                    </div>
                    <template x-ref="empty">
                        <div class="rounded-card border border-dashed border-line-strong bg-paper px-5 py-10 text-center">
                            <p class="font-semibold text-ink">Pilih kelompok untuk melihat ringkasannya.</p>
                            <p class="mt-1 text-ink-soft">Klik salah satu baris di daftar.</p>
                        </div>
                    </template>
                </aside>
            </div>
        @endif
    </div>
</x-layouts.app>
