<x-layouts.app
    title="Laporan September 2026"
    description="Contoh halaman form. Di HP navigasi bawah disembunyikan dan bar aksi menempel di bawah layar."
    :back="route('dev.components.app')"
    :navigation="$navigation"
    notifications-url="#"
    user-name="Pengguna Contoh"
    user-role="Kelompok"
    focus
>
    <form class="space-y-6" x-on:submit.prevent>
        <x-admin.form-section title="Aktivitas" description="Contoh section form dengan bar aksi yang menempel.">
            <div class="grid gap-5 md:grid-cols-2">
            <x-field label="Jumlah kegiatan" for="activity_count" optional>
                <x-input name="activity_count" type="number" min="0" inputmode="numeric" />
            </x-field>
            <x-field label="Anggota aktif" for="members_active" optional>
                <x-input name="members_active" type="number" min="0" inputmode="numeric" />
            </x-field>
            <x-field label="Kategori masalah" for="category">
                <x-select name="category" placeholder="Pilih kategori" :options="['modal' => 'Modal', 'bahan_baku' => 'Bahan baku', 'peralatan' => 'Peralatan', 'pemasaran' => 'Pemasaran', 'lainnya' => 'Lainnya']" />
            </x-field>
            <x-field label="Pendapatan penjualan (Rp)" for="income_sales" hint="Kosongkan bila belum diketahui." optional>
                <x-input name="income_sales" type="number" min="0" inputmode="numeric" />
            </x-field>
            <x-field label="Ringkasan kegiatan" for="activity_summary" class="md:col-span-2">
                <x-textarea name="activity_summary" rows="6" />
            </x-field>
            <x-field label="Capaian" for="achievements" class="md:col-span-2" optional>
                <x-textarea name="achievements" rows="6" />
            </x-field>
            </div>
        </x-admin.form-section>
        <x-admin.form-bar :cancel="route('dev.components.app')" submit="Ajukan laporan">
            <x-button variant="ghost" type="submit">Simpan draf</x-button>
        </x-admin.form-bar>
    </form>
</x-layouts.app>
