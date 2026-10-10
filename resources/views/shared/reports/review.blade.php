{{--
    Tombol dan modal peninjauan (verifikasi, minta revisi, buka kembali) di detail laporan.
    Hanya dirender untuk peninjau; setiap tombol mengikuti MonthlyReportPolicy. Variabel: $report, $area.
--}}
@php
    $canVerify = auth()->user()->can('verify', $report);
    $canReopen = auth()->user()->can('reopen', $report);
@endphp

@if ($canVerify)
    <x-modal name="report-verify" title="Verifikasi laporan" :show="$errors->has('verify_note')">
        <form id="form-verify" method="POST" action="{{ route($area.'.reports.verify', $report) }}" class="space-y-4">
            @csrf
            <p>Laporan yang sudah diverifikasi masuk ke statistik resmi dan tidak bisa diubah lagi oleh kelompok.</p>
            <x-field label="Catatan untuk kelompok" for="verify_note" optional>
                <x-textarea name="verify_note" rows="3" maxlength="2000" />
            </x-field>
        </form>
        <x-slot:footer>
            <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'report-verify')">Batal</x-button>
            <x-button type="submit" form="form-verify">Verifikasi</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal name="report-revision" title="Minta revisi" :show="$errors->has('revision_note')">
        <form id="form-revision" method="POST" action="{{ route($area.'.reports.revision', $report) }}" class="space-y-4">
            @csrf
            <p>Laporan dikembalikan ke kelompok untuk diperbaiki, lalu diajukan lagi.</p>
            <x-field label="Catatan revisi" for="revision_note" hint="Sebut bagian yang perlu diperbaiki. Catatan ini tampil di atas form laporan kelompok.">
                <x-textarea name="revision_note" rows="4" minlength="10" maxlength="2000" required />
            </x-field>
        </form>
        <x-slot:footer>
            <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'report-revision')">Batal</x-button>
            <x-button type="submit" form="form-revision">Minta revisi</x-button>
        </x-slot:footer>
    </x-modal>
@endif

@if ($canReopen)
    <x-modal name="report-reopen" title="Buka kembali untuk revisi" :show="$errors->has('reopen_note')">
        <form id="form-reopen" method="POST" action="{{ route('admin.reports.reopen', $report) }}" class="space-y-4">
            @csrf
            <p>Verifikasi dicabut dan laporan kembali ke kelompok dengan status perlu revisi. Riwayat verifikasi tetap tersimpan.</p>
            <x-field label="Alasan" for="reopen_note" hint="Wajib. Tampil untuk kelompok sebagai catatan revisi.">
                <x-textarea name="reopen_note" rows="4" minlength="10" maxlength="2000" required />
            </x-field>
        </form>
        <x-slot:footer>
            <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'report-reopen')">Batal</x-button>
            <x-button type="submit" form="form-reopen" variant="danger">Buka kembali</x-button>
        </x-slot:footer>
    </x-modal>
@endif
