{{-- Impor kelompok, langkah 1: unduh template dan unggah file. Variabel: $maxRows. --}}
<x-layouts.app
    title="Impor kelompok"
    description="Tambah banyak kelompok sekaligus dari spreadsheet."
    :back="route('admin.organizations.index')"
    focus
    :breadcrumbs="[
        ['label' => 'Kelompok', 'href' => route('admin.organizations.index')],
        ['label' => 'Impor'],
    ]"
>
    <x-slot:secondary>
        <x-button variant="secondary" icon="arrow-down-tray" :href="route('admin.organizations.import.template')">Unduh template</x-button>
    </x-slot:secondary>

    <form method="POST" action="{{ route('admin.organizations.import.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        <x-form-errors />

        <x-admin.form-section title="1. Unduh template" description="Isi sheet Data, satu baris untuk satu kelompok.">
            <p class="text-ui text-ink-soft">Sheet Petunjuk berisi cara mengisi dan daftar jenis kelembagaan, kampung, bidang usaha, serta OPD yang valid.</p>
            <div><x-button variant="secondary" icon="arrow-down-tray" :href="route('admin.organizations.import.template')">Unduh template</x-button></div>
        </x-admin.form-section>

        <x-admin.form-section title="2. Unggah file" description="Data belum disimpan. Anda melihat pratinjau dan memilih baris yang diimpor terlebih dahulu.">
            <x-field label="File .xlsx atau .csv" for="file" required>
                <x-admin.dropzone name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" :hint="'XLSX atau CSV. Maksimal 2 MB dan '.$maxRows.' baris data.'" :preview="false" required />
            </x-field>
            <p class="text-caption text-ink-muted">Kelompok hasil impor berstatus Aktif, belum tampil di direktori publik, dan belum punya akun. Akun dibuat dari tab Akun di detail kelompok.</p>
        </x-admin.form-section>

        <x-admin.form-bar :cancel="route('admin.organizations.index')" submit="Periksa file" processing="Memeriksa…" />
    </form>
</x-layouts.app>
