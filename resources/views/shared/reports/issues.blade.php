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
    <x-slot:secondary>
        {{-- Mengikuti filter yang sedang aktif (ditukar saat filter berubah). --}}
        <x-button variant="secondary" icon="arrow-down-tray" :href="route($area.'.issues.export', array_filter($filters, 'filled'))" data-swap="unduh-masalah">Unduh Excel</x-button>
    </x-slot:secondary>

    @php
        $toolbarFilters = array_values(array_filter([
            ['name' => 'kind', 'label' => 'Jenis', 'options' => IssueKind::options(), 'selected' => $filters['kind'] ?? null, 'placeholder' => 'Masalah dan kebutuhan'],
            ['name' => 'category', 'label' => 'Kategori', 'options' => $categories, 'selected' => $filters['category'] ?? null, 'placeholder' => 'Semua kategori'],
            ['name' => 'status', 'label' => 'Status tindak lanjut', 'options' => FollowUpStatus::options(), 'selected' => $filters['status'] ?? null, 'placeholder' => 'Semua status'],
            ['name' => 'village', 'label' => 'Kampung', 'options' => $villages, 'selected' => $filters['village'] ?? null, 'placeholder' => 'Semua kampung'],
            ['name' => 'period', 'label' => 'Periode', 'options' => $periods, 'selected' => $filters['period'] ?? null, 'placeholder' => 'Semua periode'],
            $opds ? ['name' => 'opd', 'label' => 'OPD pembina', 'options' => $opds, 'selected' => $filters['opd'] ?? null, 'placeholder' => 'Semua OPD'] : null,
        ]));
    @endphp

    <x-admin.card flush>
        <x-admin.toolbar
            title="Daftar masalah dan kebutuhan"
            :action="$url"
            :filters="$toolbarFilters"
            :keep="collect($filters)->only(['lama'])->all()"
            :chips="$chips"
            :summary="$summary"
            :clear-url="$chips ? $url : null"
        />

        {{-- Ditukar oleh x-admin.toolbar saat filter berubah. --}}
        <div data-swap="masalah">

        @if ($issues->isEmpty())
            @if ($chips)
                <x-admin.empty bare icon="magnifying-glass" title="Tidak ada yang cocok dengan filter" description="Ubah atau hapus filter untuk melihat data lain.">
                    <x-button variant="secondary" :href="$url">Hapus filter</x-button>
                </x-admin.empty>
            @else
                <x-admin.empty bare icon="exclamation-circle" title="Belum ada masalah atau kebutuhan" description="Isian bagian E dan F dari laporan yang sudah diajukan kelompok akan muncul di sini." />
            @endif
        @else
            <x-admin.table bare clickable>
                <x-slot:head>
                    <x-admin.table.heading>Kelompok</x-admin.table.heading>
                    <x-admin.table.heading>Status</x-admin.table.heading>
                    <x-admin.table.heading>Kategori</x-admin.table.heading>
                    <x-admin.table.heading>Uraian</x-admin.table.heading>
                </x-slot:head>

                @foreach ($issues as $issue)
                    <x-admin.table.row :href="route($area.'.issues.show', $issue)" :for="$issue->monthlyReport->organization->name">
                        <x-admin.table.cell primary>
                            {{ $issue->monthlyReport->organization->name }}
                        </x-admin.table.cell>
                        <x-admin.table.cell subtitle class="md:hidden">
                            {{ Format::month($issue->monthlyReport->period) }} · {{ $issue->monthlyReport->organization->village?->name }}
                        </x-admin.table.cell>
                        <x-admin.table.cell badge>
                            <x-admin.badge-status :status="$issue->follow_up_status" />
                        </x-admin.table.cell>
                        <x-admin.table.cell label="Kategori" class="whitespace-nowrap">
                            <x-admin.chip :icon="$issue->kind === IssueKind::Problem ? 'exclamation-triangle' : 'hand-raised'">{{ $issue->category ? $issue->categoryLabel() : 'Tanpa kategori' }}</x-admin.chip>
                            <span class="mt-1 block text-label text-ink-3">{{ $issue->kind->label() }}<span class="max-md:hidden"> · {{ Format::month($issue->monthlyReport->period) }}</span></span>
                        </x-admin.table.cell>
                        <x-admin.table.cell label="Uraian" class="max-md:items-start! max-md:text-left!">
                            <span class="line-clamp-3 md:max-w-md">{{ filled($issue->description) ? $issue->description : Format::EMPTY }}</span>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @endforeach

                <x-slot:footer>
                    <x-admin.pagination :paginator="$issues" noun="isian" />
                </x-slot:footer>
            </x-admin.table>
        @endif
        </div>
    </x-admin.card>
</x-layouts.app>
