{{--
    Detail laporan bulanan baca-saja, dipakai Kelompok dan OPD.
    Variabel: $report (dengan organization, items, issues, reviews.user, latestRevisionRequest.user.opd),
    $attachments, $back, $editUrl (null bila pengguna tidak boleh mengubah), $area ('admin'/'opd' untuk
    peninjau; tombol tinjau mengikuti Policy).
--}}
@use('App\Support\Format')
@use('App\Enums\IssueKind')

@php
    $problems = $report->issues->where('kind', IssueKind::Problem)->sortBy('id');
    $supports = $report->issues->where('kind', IssueKind::Support)->sortBy('id');
    $text = fn (?string $value) => filled($value) ? $value : Format::EMPTY;
    $area ??= null;
    $canVerify = $area && auth()->user()->can('verify', $report);
    $canReopen = $area && auth()->user()->can('reopen', $report);
@endphp

<x-layouts.app
    :title="'Laporan '.Format::month($report->period)"
    :description="$report->organization->name"
    :back="$back"
    :breadcrumbs="$breadcrumbs ?? null"
>
    @if ($editUrl || $canVerify || $canReopen)
        <x-slot:actions>
            @if ($editUrl)
                <x-button :href="$editUrl" icon="pencil-square">{{ $report->status === App\Enums\ReportStatus::Revision ? 'Perbaiki laporan' : 'Lanjutkan mengisi' }}</x-button>
            @endif
            @if ($canVerify)
                <x-button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'report-revision')">Minta revisi</x-button>
                <x-button x-data x-on:click="$dispatch('open-modal', 'report-verify')">Verifikasi</x-button>
            @endif
            @if ($canReopen)
                <x-button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'report-reopen')">Buka kembali untuk revisi</x-button>
            @endif
        </x-slot:actions>
    @endif

    <div class="space-y-5">
        @include('shared.reports.revision-note', ['fixHint' => (bool) $editUrl])

        <div class="space-y-5 xl:grid xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start xl:gap-5 xl:space-y-0">
            <div class="min-w-0 space-y-5">
                <x-card title="A. Aktivitas">
                    @if ($report->no_activity)
                        <p class="mb-4 text-ink-soft">Tidak ada aktivitas bulan ini.</p>
                    @endif
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-ink-soft">Ringkasan aktivitas</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-ink break-words">{{ $text($report->activity_summary) }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Jumlah kegiatan</dt>
                            <dd class="mt-0.5 tabular-nums text-ink">{{ Format::number($report->activity_count) }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Capaian</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-ink break-words">{{ $text($report->achievements) }}</dd>
                        </div>
                    </dl>
                </x-card>

                <section class="space-y-3">
                    <h2 class="text-card-title font-semibold text-ink">B. Produksi & penjualan</h2>
                    @if ($report->items->isEmpty())
                        <x-empty-state title="Tidak ada produk yang dilaporkan" />
                    @else
                        <x-table>
                            <x-slot:head>
                                <x-table.heading>Produk</x-table.heading>
                                <x-table.heading numeric>Produksi</x-table.heading>
                                <x-table.heading numeric>Terjual</x-table.heading>
                                <x-table.heading numeric>Pendapatan</x-table.heading>
                            </x-slot:head>
                            @foreach ($report->items->sortBy('id') as $item)
                                <x-table.row>
                                    <x-table.cell primary>{{ $item->product_name ?? Format::EMPTY }}</x-table.cell>
                                    <x-table.cell label="Produksi" numeric>{{ Format::quantity($item->production_qty) }} {{ $item->production_qty !== null ? $item->production_unit : '' }}</x-table.cell>
                                    <x-table.cell label="Terjual" numeric>{{ Format::quantity($item->sales_qty) }} {{ $item->sales_qty !== null ? $item->sales_unit : '' }}</x-table.cell>
                                    <x-table.cell label="Pendapatan" numeric>{{ Format::rupiah($item->revenue) }}</x-table.cell>
                                </x-table.row>
                            @endforeach
                        </x-table>
                    @endif
                </section>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-card title="C. Sumber daya manusia">
                        <dl class="divide-y divide-line">
                            @foreach (['Anggota aktif' => Format::number($report->members_active), 'Tenaga kerja' => Format::number($report->workforce_count), 'Perubahan anggota' => $report->members_change > 0 ? '+'.Format::number($report->members_change) : Format::number($report->members_change)] as $label => $value)
                                <div class="flex justify-between gap-4 py-2 first:pt-0 last:pb-0">
                                    <dt class="text-ink-soft">{{ $label }}</dt>
                                    <dd class="tabular-nums text-ink">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-card>

                    <x-card title="D. Keuangan">
                        <dl class="divide-y divide-line">
                            @foreach (['Pendapatan penjualan' => $report->income_sales, 'Pendapatan lain' => $report->income_other, 'Biaya' => $report->costs] as $label => $value)
                                <div class="flex justify-between gap-4 py-2 first:pt-0">
                                    <dt class="text-ink-soft">{{ $label }}</dt>
                                    <dd class="tabular-nums text-ink">{{ Format::rupiah($value) }}</dd>
                                </div>
                            @endforeach
                            <div class="flex justify-between gap-4 py-2 last:pb-0">
                                <dt class="text-ink-soft">Laba <span class="text-caption">(dihitung otomatis)</span></dt>
                                <dd class="font-semibold tabular-nums text-ink">{{ Format::rupiah($report->profit) }}</dd>
                            </div>
                        </dl>
                        @if ($report->profit === null)
                            <p class="mt-2 text-caption text-ink-muted">Laba tidak dihitung karena ada angka yang kosong.</p>
                        @endif
                    </x-card>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    @foreach (['E. Permasalahan' => [$problems, 'Tidak ada masalah yang dicatat.'], 'F. Kebutuhan dukungan' => [$supports, 'Tidak ada kebutuhan yang dicatat.']] as $title => [$rows, $empty])
                        <x-card :title="$title">
                            @if ($rows->isEmpty())
                                <p class="text-ink-soft">{{ $empty }}</p>
                            @else
                                <ul class="divide-y divide-line">
                                    @foreach ($rows as $row)
                                        <li class="py-2 first:pt-0 last:pb-0">
                                            <p class="font-semibold text-ink">{{ $row->category ? $row->categoryLabel() : 'Tanpa kategori' }}</p>
                                            @if (filled($row->description))
                                                <p class="mt-0.5 whitespace-pre-line text-ink-soft break-words">{{ $row->description }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </x-card>
                    @endforeach
                </div>

                @include('shared.attachments', [
                    'title' => 'G. Dokumentasi',
                    'description' => 'Foto kegiatan, nota, atau dokumen pendukung.',
                    'uploadUrl' => null,
                ])
            </div>

            <div class="space-y-5">
                <x-card title="Ringkasan">
                    <dl class="divide-y divide-line">
                        <div class="flex justify-between gap-4 py-2 first:pt-0">
                            <dt class="text-ink-soft">Status</dt>
                            <dd><x-badge :tone="$report->status->badgeColor()">{{ $report->status->label() }}</x-badge></dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-soft">Tenggat</dt>
                            <dd class="text-right">{{ Format::date($report->deadline()) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-soft">Diajukan</dt>
                            <dd class="text-right">
                                {{ Format::date($report->submitted_at) }}
                                @if ($report->wasSubmittedLate())
                                    <span class="block text-caption text-ink-soft">Diajukan terlambat</span>
                                @elseif ($report->isOverdue())
                                    <span class="block"><x-badge tone="fix">Lewat tenggat</x-badge></span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2 last:pb-0">
                            <dt class="text-ink-soft">Diverifikasi</dt>
                            <dd class="text-right">
                                {{ Format::date($report->verified_at) }}
                                @if ($report->verified_at && $report->verifier)
                                    <span class="block text-caption text-ink-soft">oleh {{ $report->verifier->name }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </x-card>

                <x-card title="Riwayat">
                    <ol class="space-y-3">
                        <li>
                            <p class="text-ink">Laporan dibuat</p>
                            <p class="text-caption text-ink-muted">{{ Format::dateTime($report->created_at) }}</p>
                        </li>
                        @foreach ($report->reviews as $review)
                            <li>
                                <p class="text-ink"><span class="font-semibold">{{ $review->action->label() }}</span> oleh {{ $review->user?->name ?? 'akun yang sudah dihapus' }}</p>
                                <p class="text-caption text-ink-muted">{{ Format::dateTime($review->created_at) }}</p>
                                @if (filled($review->note))
                                    <p class="mt-1 whitespace-pre-line rounded-control bg-subtle px-3 py-2 text-ink-soft break-words">{{ $review->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </x-card>
            </div>
        </div>
    </div>

    @if ($canVerify || $canReopen)
        @include('shared.reports.review')
    @endif
</x-layouts.app>
