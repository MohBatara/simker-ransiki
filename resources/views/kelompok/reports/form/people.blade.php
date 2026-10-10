<section id="bagian-c" tabindex="-1" aria-label="C. Sumber daya manusia" x-cloak x-show="section === 'c'" class="focus:outline-none">
    <x-card title="C. Sumber daya manusia" description="Isi 0 bila memang tidak ada. Kosongkan bila belum diketahui.">
        @if ($previous)
            <x-slot:actions>
                <x-button variant="secondary" icon="document-duplicate" x-on:click="copyPrevious('people')">Salin dari bulan lalu</x-button>
            </x-slot:actions>
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <x-field label="Anggota aktif" for="members_active" live="'members_active'" hint="Wajib diisi, kecuali tidak ada aktivitas.">
                <x-number-input id="members_active" model="data.members_active" live="'members_active'" />
            </x-field>
            <x-field label="Tenaga kerja" for="workforce_count" live="'workforce_count'" hint="Orang yang bekerja, termasuk yang bukan anggota." optional>
                <x-number-input id="workforce_count" model="data.workforce_count" live="'workforce_count'" />
            </x-field>
            <x-field label="Perubahan anggota" for="members_change" live="'members_change'" hint="Anggota baru dikurangi anggota yang keluar. Contoh: 2 atau -1." optional>
                <x-number-input id="members_change" model="data.members_change" live="'members_change'" signed />
            </x-field>
        </div>
    </x-card>
</section>
