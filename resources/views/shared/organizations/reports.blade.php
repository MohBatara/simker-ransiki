@use('App\Support\Format')

@if ($reports->isEmpty())
    <x-admin.empty title="Belum ada laporan" :description="'Laporan bulanan dibuat oleh akun kelompok mulai '.(Format::month($organization->reporting_starts_on)).'. Bila kelompok tidak punya HP atau akun, pilih Isi laporan.'" />
@else
    <x-admin.table clickable>
        <x-slot:head>
            <x-admin.table.heading>Periode</x-admin.table.heading>
            <x-admin.table.heading fit>Status</x-admin.table.heading>
            <x-admin.table.heading fit>Diajukan</x-admin.table.heading>
            <x-admin.table.heading numeric fit>Laba</x-admin.table.heading>
        </x-slot:head>

        @foreach ($reports as $report)
            {{-- Draf kelompok yang belum pernah diajukan tidak bisa dibuka peninjau. --}}
            @php $viewable = auth()->user()->can('view', $report); @endphp
            <x-admin.table.row :href="$viewable ? route($area.'.reports.show', [$report, 'dari' => 'kelompok']) : null">
                <x-admin.table.cell primary><span class="whitespace-nowrap" title="{{ Format::month($report->period) }}">{{ Format::monthShort($report->period) }}</span></x-admin.table.cell>
                <x-admin.table.cell badge>
                    <span class="inline-flex flex-wrap items-center justify-end gap-1.5 md:justify-start">
                        <x-admin.badge-status :status="$report->status" />
                        @unless ($viewable)
                            <span class="text-caption text-ink-soft">belum diajukan</span>
                        @endunless
                        @if ($report->isOverdue())
                            <x-admin.badge-status tone="danger" label="Terlambat" />
                        @endif
                    </span>
                </x-admin.table.cell>
                <x-admin.table.cell label="Diajukan" fit><x-admin.date :value="$report->submitted_at" /></x-admin.table.cell>
                <x-admin.table.cell label="Laba" numeric fit>{{ \App\Support\Format::orEmpty(Format::rupiah($report->profit)) }}</x-admin.table.cell>
            </x-admin.table.row>
        @endforeach

        @if ($reports->hasPages())
            <x-slot:footer><x-admin.pagination :paginator="$reports" noun="laporan" /></x-slot:footer>
        @endif
    </x-admin.table>
@endif

@if (! empty($entryPeriods))
    {{-- "Isi laporan" atas nama kelompok (OPD pembina/Admin), dibuka dari tombol di aksi header. --}}
    <x-admin.modal name="report-entry" title="Isi laporan atas nama kelompok" description="Untuk kelompok yang tidak punya HP atau akun." :show="$errors->has('period')">
        <form novalidate id="form-report-entry" method="POST" action="{{ route($area.'.reports.store', $organization) }}" class="space-y-4">
            @csrf
            <p class="text-label text-ink-3">Kolom bertanda <span class="text-fix" aria-hidden="true">*</span><span class="sr-only">bintang</span> wajib diisi.</p>
            <p>Nama Anda tercatat sebagai pengisi di riwayat laporan.</p>
            @if (App\Models\MonthlyReport::separatesDuties())
                <p class="text-ink-soft">Laporan yang Anda isi atau ajukan perlu diverifikasi oleh Admin atau petugas OPD lain.</p>
            @endif
            <x-field label="Bulan laporan" for="period" required>
                <x-select name="period" :options="$entryPeriods" :selected="old('period', array_key_first($entryPeriods))" required />
            </x-field>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="requestClose()">Batal</x-button>
            <x-button type="submit" form="form-report-entry" processing="Membuka…">Isi laporan</x-button>
        </x-slot:footer>
    </x-admin.modal>
@endif
