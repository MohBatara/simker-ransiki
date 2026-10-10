{{--
    Form laporan bulanan bertahap (bagian A–H). Isian disimpan otomatis lewat JSON (reportForm di
    resources/js/report-form.js); halaman ini hanya merender isian awal dan kerangka bagian.
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
        'saveUrl' => route('kelompok.reports.draft', $report),
        'submitFormId' => 'report-submit-form',
        'backupKey' => 'simker:laporan:'.auth()->id().':'.$report->id,
        'interval' => config('simker.reports.autosave_seconds'),
    ];
@endphp

<x-layouts.app
    :title="'Laporan '.Format::month($report->period)"
    :description="$report->status->label().' · '.($report->isOverdue() ? 'lewat tenggat ' : 'tenggat ').Format::date($deadline).', pukul 23.59 WIT'"
    :back="route('kelompok.reports.index')"
    focus
>
    <div x-data="reportForm(@js($config))" class="space-y-5">
        @include('shared.reports.revision-note', ['fixHint' => true])

        <x-alert tone="wait" x-cloak x-show="backup" title="Ada isian yang belum tersimpan di perangkat ini">
            <p>Isian ini disimpan di perangkat pada <span x-text="backupTime"></span>, tetapi belum terkirim ke server. Pulihkan isian tersebut?</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-button x-on:click="restoreBackup()">Pulihkan isian</x-button>
                <x-button variant="ghost" x-on:click="discardBackup()">Buang</x-button>
            </div>
        </x-alert>

        <div class="lg:grid lg:grid-cols-[220px_minmax(0,1fr)] lg:items-start lg:gap-6">
            {{-- Daftar bagian di layar lebar; di HP/tablet diganti pemilih bagian di atas form. --}}
            <nav aria-label="Bagian laporan" class="max-lg:hidden lg:sticky lg:top-6">
                <ol class="space-y-1">
                    <template x-for="item in sections" :key="item.key">
                        <li>
                            <button
                                type="button"
                                x-on:click="go(item.key)"
                                x-bind:aria-current="section === item.key ? 'step' : null"
                                x-bind:class="section === item.key ? 'bg-brand-tint font-semibold text-brand' : 'text-ink-soft hover:bg-subtle hover:text-ink'"
                                class="block w-full rounded-control px-3 py-2 text-left transition-colors"
                            >
                                <span class="block" x-text="item.title"></span>
                                <span x-show="item.key !== 'h' && needsAttention(item.key)" class="block text-caption font-normal text-fix">Perlu dilengkapi</span>
                            </button>
                        </li>
                    </template>
                </ol>
            </nav>

            <div class="min-w-0 space-y-5">
                <div class="space-y-2 lg:hidden">
                    <div class="flex items-center gap-3">
                        <p class="shrink-0 text-caption text-ink-soft tabular-nums" x-text="`Bagian ${index + 1} dari ${sections.length}`"></p>
                        <div class="min-w-0 flex-1">
                            <x-select x-model="section" aria-label="Pilih bagian laporan" :options="$sections->pluck('title', 'key')->all()" />
                        </div>
                    </div>
                    <div class="h-1 overflow-hidden rounded-full bg-subtle" aria-hidden="true">
                        <div class="h-full rounded-full bg-brand transition-[width]" x-bind:style="`width: ${(index + 1) / sections.length * 100}%`"></div>
                    </div>
                </div>

                @include('kelompok.reports.form.activity')
                @include('kelompok.reports.form.items')
                @include('kelompok.reports.form.people')
                @include('kelompok.reports.form.finance')
                @include('kelompok.reports.form.issues', ['key' => 'e', 'kind' => 'problems', 'categories' => App\Enums\ProblemCategory::options(), 'noun' => 'masalah'])
                @include('kelompok.reports.form.issues', ['key' => 'f', 'kind' => 'supports', 'categories' => App\Enums\SupportCategory::options(), 'noun' => 'kebutuhan'])
                @include('kelompok.reports.form.documents')
                @include('kelompok.reports.form.review')

                <x-form-actions sticky>
                    <p
                        class="min-h-5 text-caption max-md:w-full max-md:flex-none! md:mr-auto"
                        x-bind:class="statusIsProblem ? 'text-fix' : 'text-ink-muted'"
                        x-text="statusText"
                        role="status"
                    ></p>
                    <x-button variant="ghost" x-show="index > 0" x-on:click="prev()">Kembali</x-button>
                    <x-button variant="secondary" x-on:click="save({ force: true })" x-bind:disabled="saving">Simpan</x-button>
                    <x-button x-show="section !== 'h'" x-on:click="next()">Lanjut</x-button>
                    <x-button x-cloak x-show="section === 'h'" x-on:click="$dispatch('open-modal', 'report-submit')" x-bind:disabled="submitting || {{ $canSubmit ? 'false' : 'true' }}">Ajukan laporan</x-button>
                </x-form-actions>
            </div>
        </div>

        <x-modal name="report-submit" title="Ajukan laporan">
            <p>Setelah diajukan, laporan tidak bisa diubah sampai ditinjau OPD. Bila OPD meminta revisi, laporan bisa diperbaiki lagi.</p>
            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'report-submit')">Batal</x-button>
                <x-button x-on:click="submit()">Ajukan laporan</x-button>
            </x-slot:footer>
        </x-modal>

        @if ($previous)
            <x-modal name="report-copy" title="Salin dari bulan lalu">
                <p x-show="copying === 'items'">Daftar produk di bagian ini akan diganti dengan produk dan satuan dari laporan {{ $previous['period'] }}. Jumlahnya dikosongkan.</p>
                <p x-show="copying === 'people'">Jumlah anggota aktif dan tenaga kerja akan diganti dengan angka dari laporan {{ $previous['period'] }}.</p>
                <x-slot:footer>
                    <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'report-copy')">Batal</x-button>
                    <x-button x-on:click="applyCopy(copying)">Ganti isian</x-button>
                </x-slot:footer>
            </x-modal>
        @endif

        <form id="report-submit-form" method="POST" action="{{ route('kelompok.reports.submit', $report) }}" class="hidden">
            @csrf
        </form>
    </div>
</x-layouts.app>
