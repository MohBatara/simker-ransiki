<?php

use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;

/*
 * Tombol interaktif di luar popup: menu titik tiga, tab, pencarian umum (Ctrl/Cmd + K), menu akun di sidebar, menu bawah
 * HP, untuk Admin, OPD, dan Kelompok. Gagal bila ada error JavaScript atau console.
 */
beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->organization = Organization::factory()->create(['name' => 'KUB Navigasi Peramban']);
    $this->organization->opds()->attach($this->opd);
});

test('Admin: menu titik tiga, tab, pencarian Ctrl+K, dan menu akun sidebar', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Admin Peramban']));
    $page = visit('/admin/kelompok');

    // Menu titik tiga baris: Buka detail dan Ubah.
    $page->click('button[aria-label="Tindakan untuk KUB Navigasi Peramban"]')
        ->assertVisible('a:has-text("Buka detail")')
        ->assertVisible('a:has-text("Ubah")');

    // Pencarian umum lewat Ctrl + K.
    $page->keys('body', 'Control+k')->wait(0.3);
    assertPopupOpen($page, 'cari-global');
    $page->keys('#cari-global', 'Escape')->wait(0.3);
    assertPopupClosed($page, 'cari-global');

    // Menu akun di sidebar.
    $page->click('[data-sidebar-profile] button')
        ->assertVisible('[data-sidebar-profile] a:has-text("Akun saya")')
        ->assertVisible('[data-sidebar-profile] button:has-text("Keluar")');

    // Tab menu bertaut.
    $page->click('nav[aria-label="Kelompok"] a:has-text("Verifikasi profil")')->wait(0.5)
        ->assertPathIs('/admin/kelompok/verifikasi-profil')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('Admin di HP: menu bawah dan lembar Lainnya', function () {
    $this->actingAs(User::factory()->admin()->create());
    $page = visit('/admin')->resize(375, 800);

    $page->click('nav[aria-label="Menu utama"] button:has-text("Lainnya")')
        ->assertVisible('text=Menu lainnya')
        ->click('a:has-text("Pengaturan") >> visible=true')->wait(0.5)
        ->assertPathBeginsWith('/admin/pengaturan')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('OPD: tab, pencarian Ctrl+K, dan menu bawah HP', function () {
    $this->actingAs(User::factory()->opd($this->opd)->create());
    $page = visit('/opd/kelompok');

    $page->keys('body', 'Control+k')->wait(0.3);
    assertPopupOpen($page, 'cari-global');
    $page->fill('#cari-global', 'Navigasi')->keys('#cari-global', 'Enter')->wait(0.5)
        ->assertPathIs('/opd/cari')
        ->assertSee('KUB Navigasi Peramban');

    $page->resize(375, 800)
        ->click('nav[aria-label="Menu utama"] a:has-text("Laporan") >> visible=true')->wait(0.5)
        ->assertPathBeginsWith('/opd/laporan')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('Kelompok: menu akun, tambah produk lewat popup, dan menu bawah HP', function () {
    $this->actingAs(User::factory()->kelompok($this->organization)->create());
    $page = visit('/kelompok/produk');

    openPopup($page, ['header button:has-text("Tambah produk")'], 'record-form');
    $page->keys('dialog[data-modal="record-form"]', 'Escape')->wait(0.3);
    assertPopupClosed($page, 'record-form');

    $page->click('[data-sidebar-profile] button')->assertVisible('[data-sidebar-profile] a:has-text("Akun saya")');

    $page->resize(375, 800)
        ->click('nav[aria-label="Menu utama"] button:has-text("Lainnya")')
        ->assertVisible('text=Menu lainnya')
        ->click('a:has-text("Legalitas & Izin") >> visible=true')->wait(0.5)
        ->assertPathIs('/kelompok/izin')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});
