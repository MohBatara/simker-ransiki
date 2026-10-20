{{--
    Dialog peninjauan (verifikasi, minta revisi, buka kembali) di detail laporan, memakai dialog konfirmasi seragam
    x-admin.confirm. Tombol pembukanya ada di bar bawah (x-admin.action-bar) dan header. Setiap dialog mengikuti
    MonthlyReportPolicy. Variabel: $report, $area, $origin.
--}}
@php
    $canVerify = auth()->user()->can('verify', $report);
    $canReopen = auth()->user()->can('reopen', $report);
@endphp

@if ($canVerify)
    <x-admin.confirm
        name="report-verify"
        title="Verifikasi laporan"
        :action="route($area.'.reports.verify', array_filter([$report, 'dari' => $origin ?? null]))"
        form="form-verify"
        confirm="Verifikasi"
        tone="brand"
        processing="Memverifikasi…"
        :show="$errors->has('verify_note')"
        size="md"
    >
        <p>Laporan yang sudah diverifikasi masuk ke statistik resmi dan tidak bisa diubah lagi oleh kelompok.</p>
        <x-field label="Catatan untuk kelompok" for="verify_note" optional>
            <x-textarea name="verify_note" rows="3" maxlength="2000" />
        </x-field>
    </x-admin.confirm>

    <x-admin.confirm
        name="report-revision"
        title="Minta revisi"
        :action="route($area.'.reports.revision', array_filter([$report, 'dari' => $origin ?? null]))"
        form="form-revision"
        confirm="Minta revisi"
        tone="brand"
        processing="Mengirim…"
        :show="$errors->has('revision_note')"
        size="md"
    >
        <p>Laporan dikembalikan ke kelompok untuk diperbaiki, lalu diajukan lagi.</p>
        <x-field label="Catatan revisi" for="revision_note" hint="Sebut bagian yang perlu diperbaiki. Catatan ini tampil di atas form laporan kelompok." required>
            <x-textarea name="revision_note" rows="4" minlength="10" maxlength="2000" required />
        </x-field>
    </x-admin.confirm>
@endif

@if ($canReopen)
    <x-admin.confirm
        name="report-reopen"
        title="Buka kembali untuk revisi"
        :action="route('admin.reports.reopen', array_filter([$report, 'dari' => $origin ?? null]))"
        form="form-reopen"
        confirm="Buka kembali"
        processing="Memproses…"
        :show="$errors->has('reopen_note')"
        size="md"
    >
        <p>Verifikasi dicabut dan laporan kembali ke kelompok dengan status perlu revisi. Riwayat verifikasi tetap tersimpan.</p>
        <x-field label="Alasan" for="reopen_note" hint="Tampil untuk kelompok sebagai catatan revisi." required>
            <x-textarea name="reopen_note" rows="4" minlength="10" maxlength="2000" required />
        </x-field>
    </x-admin.confirm>
@endif
