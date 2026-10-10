<section id="bagian-a" tabindex="-1" aria-label="A. Aktivitas" x-cloak x-show="section === 'a'" class="focus:outline-none">
    <x-card title="A. Aktivitas" description="Kegiatan kelompok selama bulan ini.">
        <div class="space-y-5">
            <x-checkbox x-model="data.no_activity" hint="Pilih bila kelompok tidak berkegiatan bulan ini. Bagian lain menjadi opsional.">
                Tidak ada aktivitas bulan ini
            </x-checkbox>

            <x-field label="Ringkasan aktivitas" for="activity_summary" live="'activity_summary'" hint="Kegiatan utama bulan ini. Wajib diisi, kecuali tidak ada aktivitas.">
                <x-textarea
                    id="activity_summary"
                    rows="4"
                    maxlength="5000"
                    x-model="data.activity_summary"
                    invalid-if="error('activity_summary')"
                />
            </x-field>

            <div class="grid gap-5 md:grid-cols-2">
                <x-field label="Jumlah kegiatan" for="activity_count" live="'activity_count'" hint="Misalnya rapat, produksi bersama, pelatihan." optional>
                    <x-number-input id="activity_count" model="data.activity_count" live="'activity_count'" />
                </x-field>
            </div>

            <x-field label="Capaian" for="achievements" live="'achievements'" hint="Hasil yang dicapai bulan ini." optional>
                <x-textarea id="achievements" rows="3" maxlength="5000" x-model="data.achievements" />
            </x-field>
        </div>
    </x-card>
</section>
