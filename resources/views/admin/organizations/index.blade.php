@php
    $activeFilters = collect($filters)->except('q')->filter(fn ($value) => filled($value))->count();
    $hasFilters = $activeFilters > 0 || filled($filters['q'] ?? null);
@endphp

<x-layouts.app title="Semua Kelompok" description="Kelompok dan kelembagaan ekonomi di Distrik Ransiki.">
    @can('create', App\Models\Organization::class)
        <x-slot:actions>
            <x-button icon="plus" :href="route('admin.organizations.create')">Tambah kelompok</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-5">
        <form method="GET" action="{{ route('admin.organizations.index') }}" x-data="{ open: @js($activeFilters > 0) }" class="space-y-3" role="search">
            <div class="flex flex-wrap gap-2">
                <label for="q" class="sr-only">Cari nama kelompok</label>
                <x-input name="q" :value="$filters['q'] ?? ''" placeholder="Cari nama kelompok" class="min-w-0 flex-1 md:max-w-sm" />
                <x-button variant="secondary" class="md:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="filter-kelompok">
                    Filter{{ $activeFilters ? " ({$activeFilters})" : '' }}
                </x-button>
                <x-button variant="secondary" type="submit">Cari</x-button>
                @if ($hasFilters)
                    <x-button variant="ghost" :href="route('admin.organizations.index')">Hapus filter</x-button>
                @endif
            </div>

            <div id="filter-kelompok" x-bind:class="{ 'max-md:hidden': ! open }" @class(['grid gap-3 sm:grid-cols-2 lg:grid-cols-5', 'max-md:hidden' => ! $activeFilters])>
                <x-select name="type" :options="$options['types']" :selected="$filters['type'] ?? null" placeholder="Semua jenis" aria-label="Jenis kelembagaan" />
                <x-select name="village" :options="$options['villages']" :selected="$filters['village'] ?? null" placeholder="Semua kampung" aria-label="Kampung" />
                <x-select name="sector" :options="$options['sectors']" :selected="$filters['sector'] ?? null" placeholder="Semua bidang usaha" aria-label="Bidang usaha" />
                <x-select name="opd" :options="['none' => 'Belum ada OPD'] + $options['opds']" :selected="$filters['opd'] ?? null" placeholder="Semua OPD" aria-label="OPD terkait" />
                <x-select name="status" :options="$options['statuses']" :selected="$filters['status'] ?? null" placeholder="Semua status" aria-label="Status kelompok" />
            </div>
        </form>

        @if ($organizations->isEmpty())
            @if ($hasFilters)
                <x-empty-state title="Tidak ada kelompok yang cocok" description="Ubah kata pencarian atau hapus filter.">
                    <x-button variant="secondary" :href="route('admin.organizations.index')">Hapus filter</x-button>
                </x-empty-state>
            @else
                <x-empty-state title="Belum ada kelompok" description="Tambah kelompok pertama agar bisa dipantau dan mulai melapor.">
                    <x-button icon="plus" :href="route('admin.organizations.create')">Tambah kelompok</x-button>
                </x-empty-state>
            @endif
        @else
            <x-table clickable>
                <x-slot:head>
                    <x-table.heading>Nama</x-table.heading>
                    <x-table.heading>Jenis · kampung</x-table.heading>
                    <x-table.heading optional>Bidang usaha</x-table.heading>
                    <x-table.heading optional>OPD</x-table.heading>
                    <x-table.heading>Status</x-table.heading>
                </x-slot:head>

                @foreach ($organizations as $organization)
                    <x-table.row :href="route('admin.organizations.show', $organization)">
                        <x-table.cell primary>{{ $organization->name }}</x-table.cell>
                        <x-table.cell subtitle>{{ $organization->institutionType->name }} · {{ $organization->village->name }}</x-table.cell>
                        <x-table.cell label="Bidang usaha" optional>{{ $organization->sectors->pluck('name')->join(', ') }}</x-table.cell>
                        <x-table.cell label="OPD" optional>
                            @if ($organization->opds->isEmpty())
                                <x-badge tone="wait">Belum ada OPD pembina</x-badge>
                            @else
                                {{ $organization->opds->pluck('short_name')->join(', ') }}
                            @endif
                        </x-table.cell>
                        <x-table.cell badge>
                            <x-badge :tone="$organization->status->badgeColor()">{{ $organization->status->label() }}</x-badge>
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
