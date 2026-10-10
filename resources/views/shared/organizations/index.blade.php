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

    <div class="space-y-4">
        <x-filter-bar
            :action="$url"
            search-label="Cari nama kelompok"
            :search="$filters['q'] ?? ''"
            :active="$active"
            :keep="collect($filters)->only(['akun', 'laporan', 'periode'])->all()"
        >
            <x-select size="sm" name="type" :options="$options['types']" :selected="$filters['type'] ?? null" placeholder="Semua jenis" x-on:change="$el.form.requestSubmit()" aria-label="Jenis kelembagaan" />
            <x-select size="sm" name="village" :options="$options['villages']" :selected="$filters['village'] ?? null" placeholder="Semua kampung" x-on:change="$el.form.requestSubmit()" aria-label="Kampung" />
            <x-select size="sm" name="sector" :options="$options['sectors']" :selected="$filters['sector'] ?? null" placeholder="Semua bidang usaha" x-on:change="$el.form.requestSubmit()" aria-label="Bidang usaha" />
            @unless ($isOpd)
                <x-select size="sm" name="opd" :options="['none' => 'Belum ada OPD'] + $options['opds']" :selected="$filters['opd'] ?? null" placeholder="Semua OPD" x-on:change="$el.form.requestSubmit()" aria-label="OPD terkait" />
            @endunless
            <x-select size="sm" name="status" :options="$options['statuses']" :selected="$filters['status'] ?? null" placeholder="Semua status" x-on:change="$el.form.requestSubmit()" aria-label="Status kelompok" />
        </x-filter-bar>

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
            <x-table clickable>
                <x-slot:head>
                    <x-table.heading>Kelompok</x-table.heading>
                    <x-table.heading optional>Bidang usaha</x-table.heading>
                    <x-table.heading>OPD</x-table.heading>
                    <x-table.heading><span class="sr-only">Status</span></x-table.heading>
                </x-slot:head>

                @foreach ($organizations as $organization)
                    <x-table.row :href="route($area.'.organizations.show', $organization)">
                        <x-table.cell primary>
                            <span class="flex items-center gap-3">
                                <x-avatar :name="$organization->name" />
                                <span class="min-w-0">
                                    <span class="block">{{ $organization->name }}</span>
                                    <span class="block text-caption font-normal text-ink-muted">{{ $organization->institutionType->name }} · {{ $organization->village->name }}</span>
                                </span>
                            </span>
                        </x-table.cell>
                        <x-table.cell label="Bidang usaha" optional>{{ $organization->sectors->pluck('name')->join(', ') }}</x-table.cell>
                        <x-table.cell label="OPD">
                            @if ($organization->opds->isEmpty())
                                <x-badge tone="wait">Belum ada OPD pembina</x-badge>
                            @else
                                {{ $organization->opds->pluck('short_name')->join(', ') }}
                            @endif
                        </x-table.cell>
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
        @endif
    </div>
</x-layouts.app>
