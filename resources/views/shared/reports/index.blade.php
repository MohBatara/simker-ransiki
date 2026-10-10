{{--
    Daftar laporan untuk peninjau. $mode: 'queue' (antrean verifikasi OPD) atau 'all' (Semua Laporan).
    Variabel: $area, $reports, $filters, $periods, $opds (Admin saja).
--}}
@use('App\Support\Format')
@use('App\Enums\ReportStatus')

@php
    $queue = $mode === 'queue';
    $url = $queue ? route('opd.verifications.index') : route($area.'.reports.index');
    $filterKeys = $queue ? ['period'] : ['status', 'period', 'opd'];
    $activeFilters = collect($filters)->only($filterKeys)->filter(fn ($value) => filled($value))->count();
    $hasFilters = $activeFilters > 0 || filled($filters['q'] ?? null);
    $title = $queue ? 'Verifikasi Laporan' : 'Semua Laporan';
    $description = match (true) {
        $queue => 'Laporan yang diajukan kelompok binaan. Yang paling lama menunggu ada di atas.',
        $area === 'opd' => 'Laporan kelompok binaan. Draf baru bisa dibuka setelah diajukan.',
        default => 'Laporan seluruh kelompok di distrik. Draf baru bisa dibuka setelah diajukan.',
    };
@endphp

<x-layouts.app :title="$title" :description="$description">
    <div class="space-y-5">
        <form method="GET" action="{{ $url }}" x-data="{ open: @js($activeFilters > 0) }" class="space-y-3" role="search">
            <div class="flex flex-wrap gap-2">
                <label for="q" class="sr-only">Cari nama kelompok</label>
                <x-input name="q" :value="$filters['q'] ?? ''" placeholder="Cari nama kelompok" class="min-w-0 flex-1 md:max-w-sm" />
                <x-button variant="secondary" class="md:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="filter-laporan">
                    Filter{{ $activeFilters ? " ({$activeFilters})" : '' }}
                </x-button>
                <x-button variant="secondary" type="submit">Cari</x-button>
                @if ($hasFilters)
                    <x-button variant="ghost" :href="$url">Hapus filter</x-button>
                @endif
            </div>

            <div id="filter-laporan" x-bind:class="{ 'max-md:hidden': ! open }" @class(['grid gap-3 sm:grid-cols-2 lg:grid-cols-4', 'max-md:hidden' => ! $activeFilters])>
                @unless ($queue)
                    <x-select name="status" :options="ReportStatus::reviewerOptions()" :selected="$filters['status'] ?? null" placeholder="Semua status" x-on:change="$el.form.requestSubmit()" aria-label="Status laporan" />
                @endunless
                <x-select name="period" :options="$periods" :selected="$filters['period'] ?? null" placeholder="Semua periode" x-on:change="$el.form.requestSubmit()" aria-label="Periode" />
                @if ($opds)
                    <x-select name="opd" :options="$opds" :selected="$filters['opd'] ?? null" placeholder="Semua OPD" x-on:change="$el.form.requestSubmit()" aria-label="OPD pembina" />
                @endif
            </div>
        </form>

        @if ($reports->isEmpty())
            @if ($hasFilters)
                <x-empty-state title="Tidak ada laporan yang cocok" description="Ubah kata pencarian atau hapus filter.">
                    <x-button variant="secondary" :href="$url">Hapus filter</x-button>
                </x-empty-state>
            @elseif ($queue)
                <x-empty-state title="Tidak ada laporan yang menunggu verifikasi" description="Laporan yang diajukan kelompok binaan akan muncul di sini." />
            @else
                <x-empty-state title="Belum ada laporan" description="Laporan muncul di sini setelah kelompok mulai mengisi laporan bulanan." />
            @endif
        @else
            <x-table clickable>
                <x-slot:head>
                    <x-table.heading>Kelompok</x-table.heading>
                    <x-table.heading>Periode</x-table.heading>
                    <x-table.heading>Status</x-table.heading>
                    @if ($queue)
                        <x-table.heading>Diajukan</x-table.heading>
                        <x-table.heading>Menunggu</x-table.heading>
                    @else
                        <x-table.heading optional>Diajukan</x-table.heading>
                        <x-table.heading numeric optional>Laba</x-table.heading>
                    @endif
                </x-slot:head>

                @foreach ($reports as $report)
                    {{-- Draf yang belum pernah diajukan tidak bisa dibuka peninjau (404), jadi barisnya tanpa tautan. --}}
                    <x-table.row :href="$report->submitted_at ? route($area.'.reports.show', $report) : null">
                        <x-table.cell primary>{{ $report->organization->name }}</x-table.cell>
                        <x-table.cell subtitle>{{ Format::month($report->period) }}</x-table.cell>
                        <x-table.cell badge>
                            <span class="inline-flex flex-wrap items-center justify-end gap-1.5 md:justify-start">
                                <x-badge :tone="$report->status->badgeColor()">{{ $report->status->reviewerLabel() }}</x-badge>
                                @if ($queue && $report->resubmitted)
                                    <span class="text-caption text-ink-soft">Diajukan ulang</span>
                                @endif
                            </span>
                        </x-table.cell>
                        @if ($queue)
                            <x-table.cell label="Diajukan">{{ Format::date($report->last_submitted_at) }}</x-table.cell>
                            <x-table.cell label="Menunggu">{{ Format::waitingSince($report->last_submitted_at) }}</x-table.cell>
                        @else
                            <x-table.cell label="Diajukan" optional>
                                {{ Format::date($report->submitted_at) }}
                                @if ($report->wasSubmittedLate())
                                    <span class="block text-caption text-ink-soft">Diajukan terlambat</span>
                                @endif
                            </x-table.cell>
                            <x-table.cell label="Laba" numeric optional>{{ Format::rupiah($report->profit) }}</x-table.cell>
                        @endif
                    </x-table.row>
                @endforeach

                @if ($reports->hasPages())
                    <x-slot:footer>{{ $reports->links() }}</x-slot:footer>
                @endif
            </x-table>
        @endif
    </div>
</x-layouts.app>
