<?php

use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\Village;

/*
 * Form di dalam popup dengan klik sungguhan: simpan kosong → halaman kembali dengan popup yang sama terbuka dan pesan di
 * bawah isian; isi benar → tersimpan, popup tertutup, toast tampil, daftar diperbarui. Gagal bila ada error JS/console.
 */
beforeEach(function () {
    $this->opd = Opd::factory()->create(['name' => 'Dinas Form Peramban']);
    $this->organization = Organization::factory()->create(['name' => 'KUB Form Peramban']);
    $this->organization->opds()->attach($this->opd);
    $this->actingAs(User::factory()->admin()->create());
});

test('popup tambah produk: kosong ditolak di popup, lalu tersimpan', function () {
    $dialog = 'dialog[data-modal="record-form"]';
    $page = visit("/admin/kelompok/{$this->organization->id}/produk");

    openPopup($page, ['header button:has-text("Tambah produk")'], 'record-form');
    $page->click($dialog.' footer button:has-text("Simpan")')->wait(1);
    assertPopupOpen($page, 'record-form');
    $page->assertSeeIn($dialog, 'Nama produk wajib diisi.');

    $page->fill($dialog.' input[name="name"]', 'Keripik Sagu Peramban')
        ->click($dialog.' footer button:has-text("Simpan")')->wait(1);
    assertPopupClosed($page, 'record-form');
    $page->assertSee('Keripik Sagu Peramban')->assertNoJavaScriptErrors()->assertNoConsoleLogs();

    expect(Product::where('name', 'Keripik Sagu Peramban')->where('organization_id', $this->organization->id)->exists())->toBeTrue();
});

test('popup tambah izin: kosong ditolak di popup, lalu tersimpan', function () {
    $dialog = 'dialog[data-modal="record-form"]';
    $page = visit("/admin/kelompok/{$this->organization->id}/izin");

    openPopup($page, ['header button:has-text("Tambah izin")'], 'record-form');
    $page->click($dialog.' footer button:has-text("Simpan")')->wait(1);
    assertPopupOpen($page, 'record-form');
    assertPopupHasText($page, 'record-form', 'wajib diisi');

    $page->fill($dialog.' input[name="type"]', 'NIB Peramban')
        ->select($dialog.' select[name="status"]', 'in_process')
        ->click($dialog.' footer button:has-text("Simpan")')->wait(1);
    assertPopupClosed($page, 'record-form');
    $page->assertSee('NIB Peramban')->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('popup tambah akun: kosong ditolak di popup, lalu tersimpan dan detail akun terbuka', function () {
    $dialog = 'dialog[data-modal="user-create"]';
    $page = visit('/admin/pengaturan/pengguna');

    openPopup($page, ['header button:has-text("Tambah akun")'], 'user-create');
    $page->click($dialog.' footer button:has-text("Simpan akun")')->wait(1);
    assertPopupOpen($page, 'user-create');
    assertPopupHasText($page, 'user-create', 'Nama wajib diisi.');
    assertPopupHasText($page, 'user-create', 'Peran wajib dipilih.');

    $page->fill('#user-name', 'Petugas Peramban')
        ->fill('#user-username', 'petugas.peramban')
        ->select('#user-role', 'opd')
        ->select('#user-opd_id', (string) $this->opd->id)
        ->click($dialog.' footer button:has-text("Simpan akun")')->wait(1)
        ->assertSee('Petugas Peramban')
        ->assertSee('Kata sandi sementara')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();

    expect(User::where('username', 'petugas.peramban')->exists())->toBeTrue();
});

test('popup tambah data master: kosong ditolak di popup, lalu tersimpan', function () {
    $dialog = 'dialog[data-modal="record-form"]';
    $page = visit('/admin/pengaturan/data-master/kampung');

    openPopup($page, ['header button:has-text("Tambah kampung")'], 'record-form');
    $page->click($dialog.' footer button:has-text("Simpan")')->wait(1);
    assertPopupOpen($page, 'record-form');
    assertPopupHasText($page, 'record-form', 'wajib diisi');

    $page->fill($dialog.' input[name="name"]', 'Kampung Peramban')
        ->click($dialog.' footer button:has-text("Simpan")')->wait(1);
    assertPopupClosed($page, 'record-form');
    $page->assertSee('Kampung Peramban')->assertNoJavaScriptErrors()->assertNoConsoleLogs();

    expect(Village::where('name', 'Kampung Peramban')->exists())->toBeTrue();
});

test('popup berisi isian: menutup setelah mengetik meminta "Buang perubahan?"', function () {
    $dialog = 'dialog[data-modal="record-form"]';
    $page = visit("/admin/kelompok/{$this->organization->id}/produk");

    openPopup($page, ['header button:has-text("Tambah produk")'], 'record-form');
    $page->type($dialog.' input[name="name"]', 'Belum disimpan')
        ->click($dialog.' button[aria-label="Tutup"]')->wait(0.3)
        ->assertSeeIn($dialog, 'Buang perubahan?');
    assertPopupOpen($page, 'record-form');

    $page->click($dialog.' button:has-text("Kembali mengisi")')->wait(0.3);
    assertPopupOpen($page, 'record-form');
    $page->click($dialog.' button[aria-label="Tutup"]')->click($dialog.' button:has-text("Buang")')->wait(0.3);
    assertPopupClosed($page, 'record-form');
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});
