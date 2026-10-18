{{--
    Detail satu masalah/kebutuhan untuk Admin dan OPD pembina: isi, form ubah status tindak lanjut, dan riwayat.
    Variabel: $area, $issue, $report, $canFollowUp, $opds (Admin saja), $back.
--}}
@use('App\Support\Format')
@use('App\Enums\FollowUpStatus')

@php
    $organization = $report->organization;
    $title = ($issue->category ? $issue->categoryLabel() : 'Tanpa kategori');
    $today = now(config('simker.reports.timezone'))->format('Y-m-d');
@endphp

<x-layouts.app
    :title="$issue->kind->label().': '.$title"
    :description="$organization->name.' · laporan '.Format::month($report->period)"
    :back="$back"
    :breadcrumbs="[['label' => 'Laporan', 'href' => route($area.'.reports.index')], ['label' => 'Masalah & kebutuhan', 'href' => $back], ['label' => $issue->kind->label().': '.$title]]"
>
    <x-slot:badge>
        <x-badge :tone="$issue->follow_up_status->badgeColor()">{{ $issue->follow_up_status->label() }}</x-badge>
    </x-slot:badge>

    <div class="space-y-5 xl:grid xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start xl:gap-5 xl:space-y-0">
        <div class="min-w-0 space-y-5">
            <x-card :title="$issue->kind->label()">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-ink-soft">Uraian</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-ink break-words">{{ filled($issue->description) ? $issue->description : Format::EMPTY }}</dd>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-ink-soft">Kelompok</dt>
                            <dd class="mt-0.5"><x-link :href="route($area.'.organizations.show', $organization)" inline>{{ $organization->name }}</x-link></dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Kampung</dt>
                            <dd class="mt-0.5 text-ink">{{ $organization->village?->name ?? Format::EMPTY }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">Laporan</dt>
                            <dd class="mt-0.5"><x-link :href="route($area.'.reports.show', [$report, 'dari' => 'masalah'])" inline>Laporan {{ Format::month($report->period) }}</x-link></dd>
                        </div>
                        <div>
                            <dt class="text-ink-soft">OPD penanggung jawab</dt>
                            <dd class="mt-0.5 text-ink">{{ $issue->followUpOpd?->name ?? 'Belum ditentukan' }}</dd>
                        </div>
                    </div>
                </dl>
            </x-card>

            @if ($canFollowUp)
                <x-card title="Ubah status tindak lanjut" description="Catatan tindakan tampil untuk kelompok. Kelompok mendapat notifikasi bila statusnya berubah.">
                    <form method="POST" action="{{ route($area.'.issues.follow-up', $issue) }}" class="space-y-5">
                        @csrf
                        <x-form-errors />
                        <div class="grid gap-5 md:grid-cols-2">
                            <x-field label="Status" for="status">
                                <x-select name="status" :options="FollowUpStatus::options()" :selected="$issue->follow_up_status->value" required />
                            </x-field>
                            <x-field label="Tanggal tindakan" for="acted_on">
                                <x-input type="date" name="acted_on" :value="$today" :max="$today" required />
                            </x-field>
                            @if ($opds)
                                <x-field label="OPD penanggung jawab" for="opd_id" optional class="md:col-span-2">
                                    <x-select name="opd_id" :options="$opds" :selected="$issue->follow_up_opd_id ?? array_key_first($opds)" placeholder="Belum ditentukan" />
                                </x-field>
                            @endif
                        </div>
                        <x-field label="Catatan tindakan" for="note" hint="Apa yang sudah atau akan dilakukan, mis. jadwal pelatihan atau alasan tidak dapat ditindaklanjuti.">
                            <x-textarea name="note" rows="4" minlength="5" maxlength="2000" required />
                        </x-field>
                        <x-form-actions>
                            <x-button type="submit" processing="Menyimpan…">Simpan</x-button>
                        </x-form-actions>
                    </form>
                </x-card>
            @endif
        </div>

        <x-card title="Riwayat tindak lanjut">
            @if ($issue->followUps->isEmpty())
                <p class="text-ink-soft">Belum ada tindak lanjut. Status awal: Baru.</p>
            @else
                @include('shared.reports._follow-ups', ['followUps' => $issue->followUps])
            @endif
        </x-card>
    </div>
</x-layouts.app>
