{{--
    "Masalah & Kebutuhan" untuk Admin (seluruh distrik) dan OPD (kelompok binaan), dari laporan yang
    diajukan atau terverifikasi, beserta status tindak lanjutnya. Baris membuka detail + riwayat.
    Variabel: $area, $issues, $filters, $kindCounts, $categories, $villages, $periods, $opds.
--}}
@use('App\Support\Format')
@use('App\Enums\IssueKind')
@use('App\Enums\FollowUpStatus')

@php
    $url = route($area.'.issues.index');
    $active = collect($filters)->only(['kind', 'category', 'status', 'village', 'period', 'opd'])->filter(fn ($value) => filled($value))->count();
    $staleDays = config('simker.follow_up.stale_days');
    $chips = \App\Support\FilterChips::from($filters, [
        'kind' => ['Jenis', IssueKind::options()],
        'category' => ['Kategori', $categories],
        'status' => ['Status', FollowUpStatus::options()],
        'village' => ['Kampung', $villages],
        'period' => ['Periode', $periods],
        'opd' => ['OPD', $opds],
        'lama' => [null, [(string) $staleDays => "Belum ditindaklanjuti lebih dari {$staleDays} hari"]],
    ]);
    $summary = collect([
        $kindCounts['problem'] ? $kindCounts['problem'].' masalah' : null,
        $kindCounts['support'] ? $kindCounts['support'].' kebutuhan' : null,
    ])->filter()->join(' · ') ?: null;
@endphp

<x-layouts.app
    title="Masalah & kebutuhan"
    section="laporan"
    tab="masalah"
    :description="($area === 'opd' ? 'Dari laporan kelompok binaan' : 'Dari laporan seluruh kelompok').' yang sudah diajukan atau terverifikasi.'"
>
    <x-slot:actions>
        {{-- Mengikuti filter yang sedang aktif. --}}
        <x-button variant="secondary" icon="arrow-down-tray" :href="route($area.'.issues.export', array_filter($filters, 'filled'))">Unduh Excel</x-button>
    </x-slot:actions>

    <div class="space-y-4">
        <x-filter-bar :action="$url" :active="$active" :keep="collect($filters)->only(['lama'])->all()">
            <x-select size="sm" name="kind" :options="IssueKind::options()" :selected="$filters['kind'] ?? null" placeholder="Masalah dan kebutuhan" x-on:change="$el.form.requestSubmit()" aria-label="Jenis" />
            <x-select size="sm" name="category" :options="$categories" :selected="$filters['category'] ?? null" placeholder="Semua kategori" x-on:change="$el.form.requestSubmit()" aria-label="Kategori" />
            <x-select size="sm" name="status" :options="FollowUpStatus::options()" :selected="$filters['status'] ?? null" placeholder="Semua status" x-on:change="$el.form.requestSubmit()" aria-label="Status tindak lanjut" />
            <x-select size="sm" name="village" :options="$villages" :selected="$filters['village'] ?? null" placeholder="Semua kampung" x-on:change="$el.form.requestSubmit()" aria-label="Kampung" />
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
                    <x-table.heading>Status</x-table.heading>
                    <x-table.heading>Kategori</x-table.heading>
                    <x-table.heading>Uraian</x-table.heading>
                </x-slot:head>

                @foreach ($issues as $issue)
                    <x-table.row :href="route($area.'.issues.show', $issue)">
                        <x-table.cell primary>
                            {{ $issue->monthlyReport->organization->name }}
                        </x-table.cell>
                        <x-table.cell subtitle class="md:hidden">
                            {{ Format::month($issue->monthlyReport->period) }} · {{ $issue->monthlyReport->organization->village?->name }}
                        </x-table.cell>
                        <x-table.cell badge>
                            <x-badge :tone="$issue->follow_up_status->badgeColor()">{{ $issue->follow_up_status->label() }}</x-badge>
                        </x-table.cell>
                        <x-table.cell label="Kategori" class="whitespace-nowrap">
                            {{ $issue->category ? $issue->categoryLabel() : 'Tanpa kategori' }}
                            <span class="block text-caption text-ink-soft">{{ $issue->kind->label() }}<span class="max-md:hidden"> · {{ Format::month($issue->monthlyReport->period) }}</span></span>
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
