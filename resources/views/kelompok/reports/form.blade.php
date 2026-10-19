{{--
    Form laporan bulanan bertahap (bagian A–H). Isian disimpan otomatis lewat JSON (reportForm di
    resources/js/report-form.js); halaman ini hanya merender isian awal dan kerangka bagian.
    Dipakai Kelompok dan OPD/Admin yang mengisi atas nama kelompok ($onBehalf). Data dari ReportForm::page().
--}}
@use('App\Support\Format')
@use('App\Support\ReportForm')

@php
    $sections = collect(ReportForm::SECTIONS)->map(fn ($title, $key) => ['key' => $key, 'title' => strtoupper($key).'. '.$title])->values();
    $deadline = $report->deadline();
    $config = [
        'state' => $state,
        'version' => $report->updated_at?->getTimestamp(),
        'errors' => $errors->getMessages(),
        'sections' => $sections,
        'products' => $products->map->only(['id', 'name', 'unit'])->values(),
        'previous' => $previous,
        'attachmentCount' => $attachments->count(),
        'saveUrl' => $urls['save'],
        'submitFormId' => 'report-submit-form',
        'backupKey' => 'simker:laporan:'.auth()->id().':'.$report->id,
        'interval' => config('simker.reports.autosave_seconds'),
    ];
@endphp

<x-layouts.app
    :title="'Laporan '.Format::month($report->period)"
    :description="($onBehalf ? $report->organization->name.' · ' : '').$report->status->label().' · '.($report->isOverdue() ? 'lewat tenggat ' : 'tenggat ').Format::date($deadline).', pukul 23.59 WIT'"
    :back="$urls['back']"
    focus
>
    <div x-data="reportForm(@js($config))" class="space-y-6">
        <p class="text-label text-ink-3">Kolom bertanda <span class="text-fix" aria-hidden="true">*</span><span class="sr-only">bintang</span> wajib diisi saat laporan diajukan.</p>
        @include('shared.reports.revision-note', ['fixHint' => true])

        @if ($onBehalf)
            <x-alert title="Mengisi atas nama kelompok">
                Anda mengisi laporan {{ $report->organization->name }}. Riwayat laporan mencatat nama Anda sebagai pengisi.
                @if (App\Models\MonthlyReport::separatesDuties())
                    Laporan yang Anda isi atau ajukan perlu diverifikasi oleh Admin atau petugas OPD lain.
                @endif
            </x-alert>
        @endif

        <x-alert tone="wait" x-cloak x-show="backup" title="Ada isian yang belum tersimpan di perangkat ini">
            <p>Isian ini disimpan di perangkat pada <span x-text="backupTime"></span>, tetapi belum terkirim ke server. Pulihkan isian tersebut?</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-button x-on:click="restoreBackup()">Pulihkan isian</x-button>
                <x-button variant="ghost" x-on:click="discardBackup()">Buang</x-button>
            </div>
        </x-alert>

        {{--
            Stepper A sampai H di atas form: setiap bagian menampilkan statusnya (sedang diisi, perlu dilengkapi, atau
            lengkap). HP: digeser horizontal, judul hanya untuk bagian yang aktif.
        --}}
        <nav aria-label="Bagian laporan" class="rounded-tile border border-line bg-paper p-2 md:p-3">
            <p class="px-2 pb-2 text-caption text-ink-soft tabular-nums md:hidden" x-text="`Bagian ${index + 1} dari ${sections.length}`"></p>
            <ol class="scrollbar-none flex gap-1 overflow-x-auto">
                <template x-for="(item, position) in sections" :key="item.key">
                    <li class="shrink-0 lg:min-w-0 lg:flex-1">
                        <button
                            type="button"
                            x-on:click="go(item.key)"
                            x-bind:aria-current="section === item.key ? 'step' : null"
                            x-bind:aria-label="`${item.title}: ${section === item.key ? 'sedang diisi' : (item.key !== 'h' && needsAttention(item.key) ? 'perlu dilengkapi' : 'lengkap')}`"
                            x-bind:class="section === item.key ? 'bg-brand-tint' : 'hover:bg-subtle'"
                            class="flex min-h-11 w-full items-center gap-2 rounded-control px-2 py-1.5 text-left transition-colors"
                        >
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-full border text-caption font-semibold"
                                x-bind:class="section === item.key ? 'border-brand bg-brand text-paper' : (item.key !== 'h' && needsAttention(item.key) ? 'border-fix bg-fix-bg text-fix' : 'border-line-strong bg-paper text-ink-soft')"
                                aria-hidden="true"
                            >
                                <span x-text="item.key.toUpperCase()"></span>
                            </span>
                            <span class="min-w-0" x-bind:class="section === item.key ? '' : 'max-lg:hidden'">
                                <span class="block truncate text-caption font-semibold" x-bind:class="section === item.key ? 'text-brand' : 'text-ink'" x-text="item.title.slice(3)"></span>
                                <span class="block truncate text-caption" x-bind:class="item.key !== 'h' && needsAttention(item.key) ? 'text-fix' : 'text-ink-muted'" x-text="item.key === 'h' ? 'Periksa' : (needsAttention(item.key) ? 'Perlu dilengkapi' : 'Lengkap')"></span>
                            </span>
                        </button>
                    </li>
                </template>
            </ol>
        </nav>

        <div class="min-w-0 space-y-6">
            @include('kelompok.reports.form.activity')
            @include('kelompok.reports.form.items')
            @include('kelompok.reports.form.people')
            @include('kelompok.reports.form.finance')
            @include('kelompok.reports.form.issues', ['key' => 'e', 'kind' => 'problems', 'categories' => App\Enums\ProblemCategory::options(), 'noun' => 'masalah'])
            @include('kelompok.reports.form.issues', ['key' => 'f', 'kind' => 'supports', 'categories' => App\Enums\SupportCategory::options(), 'noun' => 'kebutuhan'])
            @include('kelompok.reports.form.documents')
            @include('kelompok.reports.form.review')

            {{-- Bar aksi menempel: status simpan otomatis + Kembali / Simpan / Lanjut atau Ajukan. --}}
            <x-admin.action-bar>
                <p
                    class="min-h-5 text-caption max-md:w-full md:mr-auto"
                    x-bind:class="statusIsProblem ? 'text-fix' : 'text-ink-muted'"
                    x-text="statusText"
                    role="status"
                ></p>
                <x-button variant="ghost" x-show="index > 0" x-on:click="prev()">Kembali</x-button>
                <x-button variant="secondary" x-on:click="save({ force: true })" x-bind:disabled="saving">Simpan</x-button>
                <x-button x-show="section !== 'h'" x-on:click="next()">Lanjut</x-button>
                <x-button x-cloak x-show="section === 'h'" x-on:click="$dispatch('open-modal', 'report-submit')" x-bind:disabled="submitting || {{ $canSubmit ? 'false' : 'true' }}">Ajukan laporan</x-button>
            </x-admin.action-bar>
        </div>

        <x-modal name="report-submit" title="Ajukan laporan">
            <p>Setelah diajukan, laporan tidak bisa diubah sampai ditinjau OPD. Bila OPD meminta revisi, laporan bisa diperbaiki lagi.</p>
            <x-slot:footer>
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'report-submit')">Batal</x-button>
                <x-button x-on:click="submit()">Ajukan laporan</x-button>
            </x-slot:footer>
        </x-modal>

        @if ($previous)
            <x-modal name="report-copy" title="Salin dari bulan lalu">
                <p x-show="copying === 'items'">Daftar produk di bagian ini akan diganti dengan produk dan satuan dari laporan {{ $previous['period'] }}. Jumlahnya dikosongkan.</p>
                <p x-show="copying === 'people'">Jumlah anggota aktif dan tenaga kerja akan diganti dengan angka dari laporan {{ $previous['period'] }}.</p>
                <x-slot:footer>
                    <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'report-copy')">Batal</x-button>
                    <x-button x-on:click="applyCopy(copying)">Ganti isian</x-button>
                </x-slot:footer>
            </x-modal>
        @endif

        <form novalidate id="report-submit-form" method="POST" action="{{ $urls['submit'] }}" class="hidden">
            @csrf
        </form>
    </div>
</x-layouts.app>
