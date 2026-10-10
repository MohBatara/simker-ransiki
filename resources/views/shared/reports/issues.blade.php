{{--
    "Masalah & Kebutuhan" untuk Admin (seluruh distrik) dan OPD (kelompok binaan), dari laporan yang
    diajukan atau terverifikasi. Variabel: $area, $issues, $filters, $categories, $periods, $opds.
--}}
@use('App\Support\Format')
@use('App\Enums\IssueKind')

@php
    $url = route($area.'.issues.index');
    $active = collect($filters)->only(['kind', 'category', 'period', 'opd'])->filter(fn ($value) => filled($value))->count();
    $chips = \App\Support\FilterChips::from($filters, [
        'kind' => ['Jenis', IssueKind::options()],
        'category' => ['Kategori', $categories],
        'period' => ['Periode', $periods],
        'opd' => ['OPD', $opds],
    ]);
    $summary = collect([
        $kindCounts['problem'] ? $kindCounts['problem'].' masalah' : null,
        $kindCounts['support'] ? $kindCounts['support'].' kebutuhan' : null,
    ])->filter()->join(' · ') ?: null;
@endphp

<x-layouts.app
    title="Masalah & Kebutuhan"
    :description="($area === 'opd' ? 'Dari laporan kelompok binaan' : 'Dari laporan seluruh kelompok').' yang sudah diajukan atau terverifikasi.'"
>
    <div class="space-y-4">
        <x-filter-bar :action="$url" :active="$active">
            <x-select size="sm" name="kind" :options="IssueKind::options()" :selected="$filters['kind'] ?? null" placeholder="Masalah dan kebutuhan" x-on:change="$el.form.requestSubmit()" aria-label="Jenis" />
            <x-select size="sm" name="category" :options="$categories" :selected="$filters['category'] ?? null" placeholder="Semua kategori" x-on:change="$el.form.requestSubmit()" aria-label="Kategori" />
            <x-select size="sm" name="period" :options="$periods" :selected="$filters['period'] ?? null" placeholder="Semua periode" x-on:change="$el.form.requestSubmit()" aria-label="Periode" />
            @if ($opds)
                <x-select size="sm" name="opd" :options="$opds" :selected="$filters['opd'] ?? null" placeholder="Semua OPD" x-on:change="$el.form.requestSubmit()" aria-label="OPD pembina" />
            @endif
        </x-filter-bar>

        <x-filter-summary :summary="$summary" :chips="$chips" :clear-url="$url" />

        @if ($issues->isEmpty())
            @if ($chips)
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
