<?php

use App\Models\Organization;
use App\Models\User;
use Symfony\Component\Finder\Finder;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->organization = Organization::factory()->create(['name' => 'KUB Form Contoh']);
});

test('form tambah/ubah: kalimat kolom wajib, tanpa label (opsional), remah form', function () {
    $this->actingAs($this->admin);

    $this->get('/admin/kelompok/tambah')->assertOk()
        ->assertSee('wajib diisi.')
        ->assertDontSee('(opsional)')
        ->assertSeeInOrder(['aria-label="Remah"', 'Kelompok', 'Tambah kelompok'], false);

    $this->get("/admin/kelompok/{$this->organization->id}/ubah")->assertOk()
        ->assertSeeInOrder(['aria-label="Remah"', 'Kelompok', 'KUB Form Contoh', 'Ubah profil'], false)
        ->assertSee('placeholder="08xx xxxx xxxx"', false)
        ->assertSee('placeholder="2016"', false);

    $this->get('/admin/pengaturan/pengguna/tambah')->assertOk()
        ->assertSee('wajib diisi.')
        ->assertDontSee('(opsional)')
        ->assertSeeInOrder(['aria-label="Remah"', 'Pengguna', 'Tambah akun'], false);
});

test('form panjang (lebih dari 3 section) memakai daftar section yang menempel; form pendek tidak', function () {
    $this->actingAs($this->admin);

    $this->get("/admin/kelompok/{$this->organization->id}/ubah")->assertOk()
        ->assertSee('x-data="formNav"', false)
        ->assertSee('aria-label="Bagian form"', false)
        ->assertSee('data-form-section data-title="Identitas"', false);

    $this->get('/admin/pengaturan/pengguna/tambah')->assertOk()->assertDontSee('x-data="formNav"', false);
    $this->get('/admin/website/berita/tambah')->assertOk()->assertDontSee('x-data="formNav"', false);
});

test('validasi gagal: ringkasan jumlah isian, isian berbingkai merah dengan pesan di bawahnya', function () {
    $this->actingAs($this->admin)
        ->from('/admin/kelompok/tambah')
        ->post('/admin/kelompok', ['name' => '', 'institution_type_id' => '', 'village_id' => ''])
        ->assertRedirect('/admin/kelompok/tambah');

    $this->get('/admin/kelompok/tambah')->assertOk()
        ->assertSee('data-form-errors', false)
        ->assertSeeText('isian yang perlu diperbaiki')
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('border-fix', false);
});

test('bar aksi form: Batal dan Simpan, info perubahan belum disimpan dengan peringatan saat meninggalkan halaman', function () {
    $this->actingAs($this->admin)->get("/admin/kelompok/{$this->organization->id}/ubah")->assertOk()
        ->assertSee('x-data="formGuard(', false)
        ->assertSee('Perubahan belum disimpan')
        ->assertSeeInOrder(['Batal', 'Simpan perubahan']);
});

test('semua form memakai novalidate: tidak ada pesan bawaan browser, validasi di server', function () {
    $missing = collect(Finder::create()->files()->in(resource_path('views'))->name('*.blade.php'))
        ->flatMap(function ($file) {
            preg_match_all('/<form(?=[\s>])[^>]*>/s', $file->getContents(), $forms);

            return collect($forms[0])->reject(fn (string $tag) => str_contains($tag, 'novalidate'))
                ->map(fn () => $file->getRelativePathname());
        })
        ->unique()->values();

    expect($missing->all())->toBe([]);
});

test('pesan validasi berbahasa Indonesia dengan nama isian yang ramah; isian pilihan "wajib dipilih"', function () {
    $this->actingAs($this->admin)->from('/admin/kelompok/tambah')
        ->post('/admin/kelompok', ['name' => '', 'leader_name' => 'Ketua Tetap Ada'])
        ->assertRedirect('/admin/kelompok/tambah');

    // Ringkasan + pesan per isian; isian lain yang sudah diisi tidak hilang; grup centang bisa dituju dan berbingkai merah.
    $this->get('/admin/kelompok/tambah')->assertOk()
        ->assertSeeText('Nama kelompok wajib diisi.')
        ->assertSeeText('Jenis kelembagaan wajib dipilih.')
        ->assertSeeText('Kampung wajib dipilih.')
        ->assertSeeText('Pilih minimal satu bidang usaha.')
        ->assertSee('value="Ketua Tetap Ada"', false)
        ->assertSee('href="#sectors"', false)
        ->assertSee('id="sectors" tabindex="-1"', false)
        ->assertSee('data-field-error', false);

    $this->from('/admin/website/berita/tambah')->post('/admin/website/berita', [])
        ->assertSessionHasErrors(['title' => 'Judul wajib diisi.', 'body' => 'Isi berita wajib diisi.']);

    $this->from('/admin/pengaturan/pengguna/tambah')->post('/admin/pengaturan/pengguna', [])
        ->assertSessionHasErrors(['name' => 'Nama wajib diisi.', 'role' => 'Peran wajib dipilih.']);
});
