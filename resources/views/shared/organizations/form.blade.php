{{--
    Bagian form profil kelompok, dipakai Admin, OPD, dan Kelompok.
    Variabel: $organization, $options (App\Support\OrganizationOptions), $canAdminister.
    Kolom khusus admin tampil baca-saja bila $canAdminister false (server juga mengabaikannya).
--}}
@php
    $sectorIds = $organization->exists ? $organization->sectors->pluck('id')->all() : [];
    $opdIds = $organization->exists ? $organization->opds->pluck('id')->all() : [];
    $adminNote = 'Diatur oleh Admin distrik.';
    $duplicates = session('duplicates', []);
@endphp

@if ($duplicates)
    @include('shared.organizations._duplicates', ['candidates' => $duplicates, 'confirm' => true])
@endif

<x-admin.form-section title="Identitas" description="Nama, lokasi, dan kontak utama kelompok.">
    <div class="grid gap-5 md:grid-cols-2">
        @if ($canAdminister)
            <x-field label="Nama kelompok" for="name" class="md:col-span-2" required>
                <x-input name="name" :value="$organization->name" required maxlength="255" />
            </x-field>
            <x-field label="Jenis kelembagaan" for="institution_type_id" required>
                <x-select name="institution_type_id" :options="$options['types']" :selected="$organization->institution_type_id" placeholder="Pilih jenis" required />
            </x-field>
            <x-field label="Kampung" for="village_id" required>
                <x-select name="village_id" :options="$options['villages']" :selected="$organization->village_id" placeholder="Pilih kampung" required />
            </x-field>
        @else
            <x-field label="Nama kelompok" :hint="$adminNote" class="md:col-span-2"><p>{{ $organization->name }}</p></x-field>
            <x-field label="Jenis kelembagaan" :hint="$adminNote"><p>{{ $options['types'][$organization->institution_type_id] ?? \App\Support\Format::orEmpty(null) }}</p></x-field>
            <x-field label="Kampung" :hint="$adminNote"><p>{{ $options['villages'][$organization->village_id] ?? \App\Support\Format::orEmpty(null) }}</p></x-field>
        @endif
        <x-field label="Nama ketua" for="leader_name" optional>
            <x-input name="leader_name" :value="$organization->leader_name" maxlength="255" />
        </x-field>
        <x-field label="Nomor telepon" for="phone" optional>
            <x-input name="phone" type="tel" inputmode="tel" :value="$organization->phone" maxlength="30" />
        </x-field>
        <x-field label="Email" for="email" optional>
            <x-input name="email" type="email" :value="$organization->email" maxlength="255" />
        </x-field>
        <x-field label="Tahun berdiri" for="founded_year" optional>
            <x-input name="founded_year" type="number" inputmode="numeric" min="1900" :max="now()->year" :value="$organization->founded_year" />
        </x-field>
        <x-field label="Jumlah anggota" for="members_count" hint="Isi 0 bila memang tidak ada. Kosongkan bila belum diketahui." optional>
            <x-input name="members_count" type="number" inputmode="numeric" min="0" :value="$organization->members_count" />
        </x-field>
        <x-field label="Alamat" for="address" class="md:col-span-2" optional>
            <x-textarea name="address" rows="2" :value="$organization->address" />
        </x-field>
        <x-field label="Deskripsi" for="description" hint="Kegiatan utama kelompok. Tampil di direktori publik." class="md:col-span-2" optional>
            <x-textarea name="description" :value="$organization->description" />
        </x-field>
    </div>
</x-admin.form-section>

<x-admin.form-section title="Bidang usaha dan OPD" description="Bidang usaha dipakai di direktori dan statistik; OPD pembina memverifikasi laporan.">
    <div class="space-y-6">
        <x-checkbox-group label="Bidang usaha" name="sectors" hint="Pilih semua yang sesuai.">
            @foreach ($options['sectors'] as $id => $name)
                <x-checkbox name="sectors[]" :value="$id" :checked="in_array($id, $sectorIds)">{{ $name }}</x-checkbox>
            @endforeach
        </x-checkbox-group>

        @if ($canAdminister)
            <x-checkbox-group label="OPD terkait" name="opds" hint="OPD yang membina dan memverifikasi laporan kelompok ini." optional>
                @foreach ($options['opds'] as $id => $name)
                    <x-checkbox name="opds[]" :value="$id" :checked="in_array($id, $opdIds)">{{ $name }}</x-checkbox>
                @endforeach
            </x-checkbox-group>
        @else
            <x-field label="OPD terkait" :hint="$adminNote">
                <p>{{ collect($opdIds)->map(fn ($id) => $options['opds'][$id] ?? null)->filter()->join(', ') ?: 'Belum ada OPD pembina' }}</p>
            </x-field>
        @endif
    </div>
</x-admin.form-section>

<x-admin.form-section title="Informasi usaha" description="Gambaran kapasitas dan sarana usaha. Semua isian boleh dilengkapi kemudian.">
    <div class="grid gap-5 md:grid-cols-2">
        <x-field label="Kapasitas produksi" for="production_capacity" hint="Contoh: 500 kg kakao kering per bulan." optional>
            <x-textarea name="production_capacity" rows="2" :value="$organization->production_capacity" />
        </x-field>
        <x-field label="Pasar" for="markets" hint="Tempat atau pembeli utama hasil usaha." optional>
            <x-textarea name="markets" rows="2" :value="$organization->markets" />
        </x-field>
        <x-field label="Jumlah tenaga kerja" for="workforce_count" hint="Kosongkan bila belum diketahui." optional>
            <x-input name="workforce_count" type="number" inputmode="numeric" min="0" :value="$organization->workforce_count" />
        </x-field>
        <div class="hidden md:block"></div>
        <x-field label="Aset" for="assets" optional>
            <x-textarea name="assets" rows="2" :value="$organization->assets" />
        </x-field>
        <x-field label="Fasilitas" for="facilities" optional>
            <x-textarea name="facilities" rows="2" :value="$organization->facilities" />
        </x-field>
        <x-field label="Peralatan" for="equipment" class="md:col-span-2" optional>
            <x-textarea name="equipment" rows="2" :value="$organization->equipment" />
        </x-field>
    </div>
</x-admin.form-section>

<x-admin.form-section title="Legalitas" description="Status hukum kelompok. Izin usaha (NIB, PIRT, dan lainnya) dicatat di tab Izin.">
    <div class="grid gap-5 md:grid-cols-2">
        <x-field label="Status hukum" for="legal_status" required>
            <x-select name="legal_status" :options="$options['legalStatuses']" :selected="$organization->legal_status?->value ?? 'unknown'" required />
        </x-field>
        <x-field label="Nomor legalitas" for="legal_number" hint="Isi bila sudah terdaftar, mis. nomor akta atau SK." optional>
            <x-input name="legal_number" :value="$organization->legal_number" maxlength="255" />
        </x-field>
        <x-field label="Catatan legalitas" for="legal_notes" class="md:col-span-2" optional>
            <x-textarea name="legal_notes" rows="2" :value="$organization->legal_notes" />
        </x-field>
    </div>
</x-admin.form-section>

<x-admin.form-section title="Publikasi" description="Apa yang tampil di website publik. Kelompok yang ditutup tidak pernah tampil.">
    <div class="space-y-1">
        @if ($canAdminister)
            <x-checkbox name="is_public" :checked="$organization->is_public" hint="Nama, jenis, kampung, bidang usaha, deskripsi, dan produk publik akan tampil.">Tampilkan di direktori publik</x-checkbox>
        @else
            <x-field label="Tampil di direktori publik" :hint="$adminNote"><p>{{ $organization->is_public ? 'Ya' : 'Tidak' }}</p></x-field>
        @endif
        <x-checkbox name="show_contact_publicly" :checked="$organization->show_contact_publicly" hint="Nomor telepon dan email ikut tampil di halaman publik kelompok.">Tampilkan kontak di halaman publik</x-checkbox>
    </div>
</x-admin.form-section>

<x-admin.form-section title="Pelaporan" description="Status kelompok dan bulan mulai wajib lapor.">
    @if ($canAdminister)
        <div class="grid gap-5 md:grid-cols-2">
            @if ($organization->exists)
                <x-field label="Status kelompok" hint="Ubah lewat tombol … di halaman detail kelompok, disertai alasan.">
                    <p>{{ $organization->status->label() }}</p>
                </x-field>
            @else
                <x-field label="Status kelompok" for="status" hint="Kelompok yang ditutup tidak dihitung wajib lapor." required>
                    <x-select name="status" :options="$options['statuses']" :selected="$organization->status?->value ?? 'active'" required />
                </x-field>
            @endif
            <fieldset class="space-y-2">
                <legend class="text-ui font-semibold text-ink">Mulai wajib lapor <span class="text-fix" aria-hidden="true">*</span><span class="sr-only">(wajib)</span></legend>
                <div class="grid grid-cols-2 gap-2">
                    <x-select name="reporting_month" :options="$options['months']" :selected="$organization->reporting_starts_on?->month" aria-label="Bulan mulai wajib lapor" required />
                    <x-select name="reporting_year" :options="$options['years']" :selected="$organization->reporting_starts_on?->year" aria-label="Tahun mulai wajib lapor" required />
                </div>
                @if ($errors->has('reporting_month') || $errors->has('reporting_year'))
                    <p class="text-caption text-fix">{{ $errors->first('reporting_month') ?: $errors->first('reporting_year') }}</p>
                @else
                    <p class="text-caption text-ink-muted">Kelompok tidak dianggap terlambat sebelum bulan ini.</p>
                @endif
            </fieldset>
        </div>
    @else
        <div class="grid gap-5 md:grid-cols-2">
            <x-field label="Status kelompok" :hint="$adminNote"><p>{{ $organization->status->label() }}</p></x-field>
            <x-field label="Mulai wajib lapor" :hint="$adminNote"><p>{{ \App\Support\Format::orEmpty(\App\Support\Format::month($organization->reporting_starts_on)) }}</p></x-field>
        </div>
    @endif
</x-admin.form-section>
