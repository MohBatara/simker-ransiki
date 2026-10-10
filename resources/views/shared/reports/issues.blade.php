{{--
    "Masalah & Kebutuhan" untuk Admin (seluruh distrik) dan OPD (kelompok binaan), dari laporan yang
    diajukan atau terverifikasi. Variabel: $area, $issues, $filters, $categories, $periods, $opds.
--}}
@use('App\Support\Format')
@use('App\Enums\IssueKind')

@php
    $url = route($area.'.issues.index');
    $activeFilters = collect($filters)->only(['kind', 'category', 'period', 'opd'])->filter(fn ($value) => filled($value))->count();
@endphp

<x-layouts.app
    title="Masalah & Kebutuhan"
    :description="($area === 'opd' ? 'Dari laporan kelompok binaan' : 'Dari laporan seluruh kelompok').' yang sudah diajukan atau terverifikasi.'"
>
    <div class="space-y-5">
        <form method="GET" action="{{ $url }}" x-data="{ open: @js($activeFilters > 0) }" class="space-y-3">
            <div class="flex flex-wrap gap-2 md:hidden">
                <x-button variant="secondary" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="filter-masalah">
                    Filter{{ $activeFilters ? " ({$activeFilters})" : '' }}
                </x-button>
                @if ($activeFilters)
                    <x-button variant="ghost" :href="$url">Hapus filter</x-button>
                @endif
            </div>

            <div id="filter-masalah" x-bind:class="{ 'max-md:hidden': ! open }" @class(['grid gap-3 sm:grid-cols-2 lg:grid-cols-5', 'max-md:hidden' => ! $activeFilters])>
                <x-select name="kind" :options="IssueKind::options()" :selected="$filters['kind'] ?? null" placeholder="Masalah dan kebutuhan" x-on:change="$el.form.requestSubmit()" aria-label="Jenis" />
                <x-select name="category" :options="$categories" :selected="$filters['category'] ?? null" placeholder="Semua kategori" x-on:change="$el.form.requestSubmit()" aria-label="Kategori" />
                <x-select name="period" :options="$periods" :selected="$filters['period'] ?? null" placeholder="Semua periode" x-on:change="$el.form.requestSubmit()" aria-label="Periode" />
                @if ($opds)
                    <x-select name="opd" :options="$opds" :selected="$filters['opd'] ?? null" placeholder="Semua OPD" x-on:change="$el.form.requestSubmit()" aria-label="OPD pembina" />
                @endif
                @if ($activeFilters)
                    <x-button variant="ghost" :href="$url" class="max-md:hidden">Hapus filter</x-button>
                @endif
            </div>
            <noscript><x-button variant="secondary" type="submit">Terapkan filter</x-button></noscript>
        </form>

        @if ($issues->isEmpty())
            @if ($activeFilters)
                <x-empty-state title="Tidak ada yang cocok dengan filter" description="Ubah atau hapus filter untuk melihat data lain.">
                    <x-button variant="secondary" :href="$url">Hapus filter</x-button>
                </x-empty-state>
            @else
                <x-empty-state title="Belum ada masalah atau kebutuhan" description="Isian bagian E dan F dari laporan yang sudah diajukan kelompok akan muncul di sini." />
            @endif
        @else
            <x-table clickable>
                <x-slot:head>
                    <x-table.heading>Kelompok</x-table.heading>
                    <x-table.heading>Periode</x-table.heading>
                    <x-table.heading>Kategori</x-table.heading>
                    <x-table.heading>Uraian</x-table.heading>
                </x-slot:head>

                @foreach ($issues as $issue)
                    <x-table.row :href="route($area.'.reports.show', $issue->monthly_report_id)">
                        <x-table.cell primary>{{ $issue->monthlyReport->organization->name }}</x-table.cell>
                        <x-table.cell subtitle>{{ Format::month($issue->monthlyReport->period) }}</x-table.cell>
                        <x-table.cell label="Kategori" class="whitespace-nowrap">
                            {{ $issue->category ? $issue->categoryLabel() : 'Tanpa kategori' }}
                            <span class="block text-caption text-ink-soft">{{ $issue->kind->label() }}</span>
                        </x-table.cell>
                        <x-table.cell label="Uraian" class="max-md:items-start! max-md:text-left!">
                            <span class="line-clamp-3 md:max-w-md">{{ filled($issue->description) ? $issue->description : Format::EMPTY }}</span>
                        </x-table.cell>
                    </x-table.row>
                @endforeach

                @if ($issues->hasPages())
                    <x-slot:footer>{{ $issues->links() }}</x-slot:footer>
                @endif
            </x-table>
        @endif
    </div>
</x-layouts.app>
