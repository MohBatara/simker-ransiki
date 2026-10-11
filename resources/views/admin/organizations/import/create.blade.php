{{-- Impor kelompok, langkah 1: unduh template dan unggah file. Variabel: $maxRows. --}}
<x-layouts.app
    title="Impor kelompok"
    description="Tambah banyak kelompok sekaligus dari spreadsheet."
    :back="route('admin.organizations.index')"
    :breadcrumbs="[
        ['label' => 'Semua Kelompok', 'href' => route('admin.organizations.index')],
        ['label' => 'Impor'],
    ]"
>
    <div class="max-w-3xl space-y-5">
        <x-card title="1. Unduh template" description="Isi sheet Data, satu baris untuk satu kelompok. Sheet Petunjuk berisi cara mengisi dan daftar jenis kelembagaan, kampung, bidang usaha, serta OPD yang valid.">
            <x-button variant="secondary" icon="arrow-down-tray" :href="route('admin.organizations.import.template')">Unduh template</x-button>
        </x-card>

        <x-card title="2. Unggah file" description="Data belum disimpan. Anda akan melihat pratinjau dan memilih baris yang diimpor terlebih dahulu.">
            <form method="POST" action="{{ route('admin.organizations.import.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <x-field label="File .xlsx atau .csv" for="file" hint="Maksimal 2 MB dan {{ $maxRows }} baris data.">
                    <x-input type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required />
                </x-field>
                <x-form-actions>
                    <x-button type="submit" icon="arrow-up-tray" processing="Memeriksa…">Periksa file</x-button>
                </x-form-actions>
            </form>
        </x-card>

        <p class="text-caption text-ink-muted">Kelompok hasil impor berstatus Aktif, belum tampil di direktori publik, dan belum punya akun. Akun dibuat dari tab Akun di detail kelompok.</p>
    </div>
</x-layouts.app>
