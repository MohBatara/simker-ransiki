{{-- Impor kelompok, langkah 3: hasil. Variabel: $token, $fileName, $result (imported, skipped, failed). --}}
@php
    $failed = count($result['failed']);
@endphp

<x-layouts.app
    title="Hasil impor"
    :description="$fileName"
    :back="route('admin.organizations.index')"
    :breadcrumbs="[
        ['label' => 'Semua Kelompok', 'href' => route('admin.organizations.index')],
        ['label' => 'Impor', 'href' => route('admin.organizations.import.create')],
        ['label' => 'Hasil'],
    ]"
>
    <x-slot:actions>
        <x-button variant="secondary" :href="route('admin.organizations.import.create')">Impor file lain</x-button>
        <x-button :href="route('admin.organizations.index')">Lihat semua kelompok</x-button>
    </x-slot:actions>

    <div class="space-y-5">
        <div class="grid grid-cols-3 gap-3 md:gap-4">
            <x-stat label="Berhasil" :value="$result['imported']" tone="ok" hint="kelompok baru" />
            <x-stat label="Dilewati" :value="$result['skipped']" hint="baris peringatan" />
            <x-stat label="Gagal" :value="$failed" :tone="$failed ? 'fix' : null" hint="tidak diimpor" />
        </div>

        @if ($failed)
            <x-card title="Baris yang gagal" description="Unduh daftar ini, perbaiki sesuai kolom Alasan, lalu impor ulang. File ini memakai judul kolom yang sama dengan template.">
                <x-button variant="secondary" icon="arrow-down-tray" :href="route('admin.organizations.import.failed', $token)">Unduh baris gagal</x-button>
            </x-card>
        @endif

        <p class="text-caption text-ink-muted">Kelompok hasil impor berstatus Aktif dan belum tampil di direktori publik. Atur OPD, publikasi, dan akun dari detail tiap kelompok.</p>
    </div>
</x-layouts.app>
