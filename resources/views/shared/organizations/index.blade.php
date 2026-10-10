@php
    // OPD hanya melihat kelompok binaannya, jadi filter OPD tidak ditampilkan.
    $filterKeys = $area === 'opd' ? ['type', 'village', 'sector', 'status'] : ['type', 'village', 'sector', 'opd', 'status'];
    $activeFilters = collect($filters)->only($filterKeys)->filter(fn ($value) => filled($value))->count();
    // Filter dari tautan dasbor; tidak ada pilihannya di form, jadi dijelaskan dalam kalimat.
    $notices = array_filter([
        ($filters['akun'] ?? null) === 'tidak-ada' ? 'kelompok yang belum punya akun' : null,
        ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($filters['periode'] ?? '')) ? null : match ($filters['laporan'] ?? null) {
            'belum-dibuat' => 'kelompok wajib lapor yang belum membuat laporan '.\App\Support\Format::month(($filters['periode'] ?? '').'-01'),
            'belum-diajukan' => 'kelompok wajib lapor yang belum mengajukan laporan '.\App\Support\Format::month(($filters['periode'] ?? '').'-01'),
            default => null,
        },
    ]);
    $hasFilters = $activeFilters > 0 || filled($filters['q'] ?? null) || $notices;
@endphp

{{-- Dipakai Admin ("Semua Kelompok") dan OPD ("Kelompok Binaan"); isi sudah disaring scope visibleTo. --}}
<x-layouts.app
    :title="$area === 'opd' ? 'Kelompok Binaan' : 'Semua Kelompok'"
    :description="$area === 'opd' ? 'Kelompok yang dibina OPD Anda.' : 'Kelompok dan kelembagaan ekonomi di Distrik Ransiki.'"
>
    @can('create', App\Models\Organization::class)
        <x-slot:actions>
            <x-button icon="plus" :href="route('admin.organizations.create')">Tambah kelompok</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-5">
        @if ($notices)
            <x-alert>
                Menampilkan {{ implode(' dan ', $notices) }}.
                <a href="{{ route($area.'.organizations.index') }}" class="font-semibold text-brand hover:text-brand-hover">Tampilkan semua kelompok</a>
            </x-alert>
        @endif

        <form method="GET" action="{{ route($area.'.organizations.index') }}" x-data="{ open: @js($activeFilters > 0) }" class="space-y-3" role="search">
            @foreach (['akun', 'laporan', 'periode'] as $kept)
                @if (filled($filters[$kept] ?? null))
                    <input type="hidden" name="{{ $kept }}" value="{{ $filters[$kept] }}">
                @endif
            @endforeach
            <div class="flex flex-wrap gap-2">
                <label for="q" class="sr-only">Cari nama kelompok</label>
                <x-input name="q" :value="$filters['q'] ?? ''" placeholder="Cari nama kelompok" class="min-w-0 flex-1 md:max-w-sm" />
                <x-button variant="secondary" class="md:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="filter-kelompok">
                    Filter{{ $activeFilters ? " ({$activeFilters})" : '' }}
                </x-button>
                <x-button variant="secondary" type="submit">Cari</x-button>
                @if ($hasFilters)
                    <x-button variant="ghost" :href="route($area.'.organizations.index')">Hapus filter</x-button>
                @endif
            </div>

            {{-- Filter langsung diterapkan saat pilihan berubah; tombol Cari untuk pencarian nama. --}}
            <div id="filter-kelompok" x-bind:class="{ 'max-md:hidden': ! open }" @class(['grid gap-3 sm:grid-cols-2 lg:grid-cols-5', 'max-md:hidden' => ! $activeFilters])>
                <x-select name="type" :options="$options['types']" :selected="$filters['type'] ?? null" placeholder="Semua jenis" x-on:change="$el.form.requestSubmit()" aria-label="Jenis kelembagaan" />
                <x-select name="village" :options="$options['villages']" :selected="$filters['village'] ?? null" placeholder="Semua kampung" x-on:change="$el.form.requestSubmit()" aria-label="Kampung" />
                <x-select name="sector" :options="$options['sectors']" :selected="$filters['sector'] ?? null" placeholder="Semua bidang usaha" x-on:change="$el.form.requestSubmit()" aria-label="Bidang usaha" />
                @if ($area !== 'opd')
                    <x-select name="opd" :options="['none' => 'Belum ada OPD'] + $options['opds']" :selected="$filters['opd'] ?? null" placeholder="Semua OPD" x-on:change="$el.form.requestSubmit()" aria-label="OPD terkait" />
                @endif
                <x-select name="status" :options="$options['statuses']" :selected="$filters['status'] ?? null" placeholder="Semua status" x-on:change="$el.form.requestSubmit()" aria-label="Status kelompok" />
            </div>
        </form>

        @if ($organizations->isEmpty())
            @if ($hasFilters)
                <x-empty-state title="Tidak ada kelompok yang cocok" description="Ubah kata pencarian atau hapus filter.">
                    <x-button variant="secondary" :href="route($area.'.organizations.index')">Hapus filter</x-button>
                </x-empty-state>
            @else
                @if ($area === 'opd')
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
                    <x-table.heading>Nama</x-table.heading>
                    <x-table.heading>Jenis · kampung</x-table.heading>
                    <x-table.heading optional>Bidang usaha</x-table.heading>
                    <x-table.heading optional>OPD</x-table.heading>
                    <x-table.heading>Status</x-table.heading>
                </x-slot:head>

                @foreach ($organizations as $organization)
                    <x-table.row :href="route($area.'.organizations.show', $organization)">
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
