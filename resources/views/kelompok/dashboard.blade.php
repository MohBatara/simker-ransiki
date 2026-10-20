@use('App\Support\Format')

@php
    $missing = collect($checklist)->reject(fn ($item) => $item['done'])->values();
    // Draf bulan berjalan: tampil bila sudah ada, atau bila tugas utama tidak meminta mengisi laporan lain.
    $showCurrent = $current['allowed'] && ($current['report'] || ($canCreate && in_array($task['kind'], ['waiting', 'done', 'not-started'], true)));
@endphp

{{-- Tanpa kepala halaman: kartu sapaan menjadi elemen teratas, lonceng di pojok kanan atasnya (leadTools). --}}
<x-layouts.app title="Dasbor" description="Laporan bulanan, produksi, dan tindak lanjut kelompok Anda." :header="false" lead-tools>
    <x-slot:lead>
        {{-- Kartu sapaan netral untuk semua keadaan; SATU tugas utama di dalamnya, tombolnya tidak diulang di kartu lain. --}}
        <x-admin.greeting :name="auth()->user()->name" :subtitle="$organization->name" tools-space>
            <x-slot:task>
                @include('kelompok.dashboard.task')
            </x-slot:task>
        </x-admin.greeting>
    </x-slot:lead>

    {{-- Desktop: Laporan saya 8 kolom, Kelompok saya 4 kolom, sama tinggi. HP: bertumpuk dengan urutan yang sama. --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-stretch">
        <x-admin.card title="Laporan saya" flush stretch center-header class="overflow-hidden lg:col-span-8">
            <x-slot:actions>
                <x-link :href="route('kelompok.reports.index')">Semua laporan</x-link>
            </x-slot:actions>

            @if ($history->isEmpty())
                <p class="px-4 pb-4 text-ink-soft md:px-5 md:pb-5">Belum ada bulan yang wajib dilaporkan.</p>
            @else
                <x-list flush>
                    @foreach ($history as $row)
                        @php
                            $report = $row['report'];
                            $href = match (true) {
                                $report === null => route('kelompok.reports.index'),
                                $report->status->isEditable() => route('kelompok.reports.edit', $report),
                                default => route('kelompok.reports.show', $report),
                            };
                        @endphp
                        <x-list.item :href="$href" class="min-h-15 py-2!" data-period="{{ $row['period']->format('Y-m') }}">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-ink">{{ Format::month($row['period']) }}</span>
                                <span class="block text-caption text-ink-soft">
                                    @if ($report?->submitted_at)
                                        Diajukan {{ Format::dateShort($report->submitted_at) }}{{ $report->wasSubmittedLate() ? ', terlambat' : '' }}
                                    @else
                                        Belum diajukan
                                    @endif
                                </span>
                            </span>
                            <x-admin.badge-status :status="$row['state']" />
                        </x-list.item>
                    @endforeach
                </x-list>
            @endif

            @if ($olderOutstanding > 0)
                <p class="border-t border-line-soft px-4 py-3 text-ink-soft md:px-5">
                    {{ $olderOutstanding }} bulan sebelumnya belum diajukan.
                    <x-link :href="route('kelompok.reports.index')" inline>Lihat daftar laporan</x-link>
                </p>
            @endif

            @if ($showCurrent)
                {{-- Kaki kartu: draf bulan berjalan. --}}
                <div class="mt-auto flex flex-wrap items-center gap-3 border-t border-brand-100 bg-brand-50 px-4 py-3 md:px-5" data-current-report>
                    <x-heroicon-o-pencil-square class="size-5 shrink-0 text-brand" aria-hidden="true" />
                    <p class="min-w-0 flex-1 text-ink">Laporan {{ Format::month($current['period']) }} sudah bisa diisi.</p>
                    @include('kelompok.dashboard.report-action', ['period' => $current['period'], 'report' => $current['report'], 'label' => $current['report'] ? 'Lanjutkan' : 'Mulai isi', 'variant' => 'secondary', 'class' => 'max-sm:w-full max-sm:*:w-full'])
                </div>
            @endif
        </x-admin.card>

        {{-- Kelompok saya: kelengkapan profil, angka ringkas, OPD pembina. Tiap baris menaut ke halaman terkait. --}}
        <x-admin.card title="Kelompok saya" flush stretch center-header class="lg:col-span-4">
            <x-slot:actions>
                <x-link :href="route('kelompok.profile.show')">Lihat profil</x-link>
            </x-slot:actions>

            <x-list flush>
                @if ($missing->isEmpty())
                    <x-list.item :href="route('kelompok.profile.show')" data-profile-complete>
                        <x-heroicon-o-check-circle class="size-5 shrink-0 text-ok" aria-hidden="true" />
                        <span class="min-w-0 flex-1 font-semibold text-ink">Profil lengkap</span>
                    </x-list.item>
                @else
                    <x-list.item :href="route($missing->first()['route'])" data-profile-incomplete>
                        <span class="mt-0.5 size-5 shrink-0 self-start rounded-full border-2 border-wait" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-ink">Profil belum lengkap, {{ $missing->count() }} bagian lagi</span>
                            <span class="block text-caption text-ink-soft">Belum: {{ $missing->pluck('label')->map(fn ($label) => Str::lcfirst($label))->join(', ') }}</span>
                        </span>
                    </x-list.item>
                @endif
                @foreach ([
                    ['Produk', Format::number($facts['products']), route('kelompok.products.index')],
                    ['Izin tercatat', Format::number($facts['licenses']), route('kelompok.licenses.index')],
                    ['Anggota', $facts['members'] === null ? null : Format::number($facts['members']).' orang', route('kelompok.profile.show')],
                ] as [$label, $value, $href])
                    <x-list.item :href="$href">
                        <span class="min-w-0 flex-1 text-ink-soft">{{ $label }}</span>
                        @if ($value === null)
                            <x-admin.empty-value />
                        @else
                            <span class="font-semibold tabular-nums text-ink">{{ $value }}</span>
                        @endif
                    </x-list.item>
                @endforeach
            </x-list>

            {{-- OPD pembina: nama OPD, petugas aktif, telepon bila ada (bukan tautan halaman; telepon bisa diketuk). --}}
            <div class="border-t border-line-soft px-4 py-3 md:px-5" data-opd-contacts>
                <p class="text-caption text-ink-soft">OPD pembina</p>
                @forelse ($facts['opds'] as $opd)
                    <div class="mt-2">
                        <p class="font-semibold text-ink">{{ $opd->name }}</p>
                        @forelse ($opd->users as $officer)
                            <p class="flex flex-wrap items-center gap-x-2 text-ink-soft">
                                <span>{{ $officer->name }}</span>
                                @if ($officer->phone)
                                    <x-link :href="\App\Support\Format::telUrl($officer->phone)" class="font-normal">{{ \App\Support\Format::phone($officer->phone) }}</x-link>
                                @endif
                            </p>
                        @empty
                            <p class="text-ink-soft">Belum ada petugas.</p>
                        @endforelse
                    </div>
                @empty
                    <p class="mt-1 text-ink-soft">Belum ada OPD pembina. Hubungi Admin distrik.</p>
                @endforelse
            </div>
        </x-admin.card>
    </div>

    {{-- Status tindak lanjut OPD atas masalah & kebutuhan yang pernah dilaporkan (baca-saja). --}}
    @if ($followUps->isNotEmpty())
        <x-admin.card title="Tindak lanjut masalah & kebutuhan" description="Dari laporan yang sudah diajukan. Status diubah oleh OPD atau Admin distrik." class="mt-6" flush>
            <x-list flush>
                @foreach ($followUps as $issue)
                    <x-list.item :href="route('kelompok.reports.show', $issue->monthly_report_id)">
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-ink">{{ $issue->category ? $issue->categoryLabel() : 'Tanpa kategori' }}</span>
                            <span class="block text-caption text-ink-soft">{{ $issue->kind->label() }} · laporan {{ \App\Support\Format::orEmpty(Format::month($issue->monthlyReport->period)) }}</span>
                            @if ($issue->followUps->isNotEmpty())
                                <span class="mt-0.5 line-clamp-2 block text-ink-soft break-words">{{ $issue->followUps->first()->note }}</span>
                            @endif
                            {{-- HP: pil di bawah teks agar kolom teks tidak terjepit pil panjang. --}}
                            <x-admin.badge-status :status="$issue->follow_up_status" class="mt-1.5 sm:hidden" />
                        </span>
                        <x-admin.badge-status :status="$issue->follow_up_status" class="max-sm:hidden" />
                    </x-list.item>
                @endforeach
            </x-list>
        </x-admin.card>
    @endif

    {{-- Grafik 12 bulan sampai bulan wajib lapor, hanya dari laporan terverifikasi kelompok ini. --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-stretch">
        <x-chart
            title="Pendapatan penjualan, 12 bulan"
            :chart="App\Support\Dashboards\StatisticsCharts::incomeTrend($incomeTrend, coverage: false)"
            note="Bulan tanpa laporan terverifikasi tidak digambar (garis terputus), bukan dianggap 0."
        />
        <x-chart
            title="Produksi, 12 bulan"
            :chart="App\Support\Dashboards\StatisticsCharts::productionTrend($productionTrend, $months)"
        />
    </div>
</x-layouts.app>
