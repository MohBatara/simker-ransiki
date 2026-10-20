<?php

use App\Enums\OrganizationStatus;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;

/*
 * Popup Filter dan popup pratinjau kelompok dengan klik sungguhan. Gagal bila ada error JavaScript atau console.
 */
beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->alpha = Organization::factory()->create(['name' => 'Alfa Peramban', 'status' => OrganizationStatus::Active]);
    $this->beta = Organization::factory()->create(['name' => 'Beta Peramban', 'status' => OrganizationStatus::Inactive]);
    $this->gamma = Organization::factory()->create(['name' => 'Gamma Peramban', 'status' => OrganizationStatus::Active]);
    $this->alpha->opds()->attach($this->opd);
    $this->beta->opds()->attach($this->opd);
    $this->actingAs(User::factory()->admin()->create());
});

test('popup Filter: pilih filter, Terapkan menyaring daftar, Atur ulang mengembalikan semua', function () {
    $page = visit('/admin/kelompok')->assertSee('Gamma Peramban');

    openPopup($page, ['button[aria-controls="panel-filter"]'], 'filter');
    $page->select('#status-panel', 'inactive')
        // Pilihan di popup belum diterapkan sebelum Terapkan.
        ->assertSee('Gamma Peramban')
        ->click('dialog[data-modal="filter"] footer button:has-text("Terapkan")')
        ->wait(1);
    assertPopupClosed($page, 'filter');
    $page->assertQueryStringHas('status', 'inactive')
        ->assertSeeIn('#daftar-kelompok', 'Beta Peramban')
        ->assertDontSeeIn('#daftar-kelompok', 'Gamma Peramban');

    openPopup($page, ['button[aria-controls="panel-filter"]'], 'filter');
    $page->click('dialog[data-modal="filter"] footer button:has-text("Atur ulang")')->wait(1);
    assertPopupClosed($page, 'filter');
    $page->assertQueryStringMissing('status')
        ->assertSeeIn('#daftar-kelompok', 'Gamma Peramban')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('pratinjau: klik baris membuka popup tanpa pindah halaman, panah kiri/kanan, Tutup tanpa menambah riwayat', function () {
    $page = visit('/admin/kelompok');
    $history = $page->script('window.history.length');

    // Klik di sel lain baris (bukan nama) juga membuka pratinjau.
    $page->click('tr[data-preview-id="'.$this->alpha->id.'"] td:nth-child(3)')->wait(1);
    assertPopupOpen($page, 'organization-preview');
    $page->assertPathIs('/admin/kelompok')
        ->assertQueryStringHas('pratinjau', (string) $this->alpha->id)
        ->assertSeeIn('#panel-judul', 'Alfa Peramban');

    // Panah kanan (tombol keyboard) dan tombol panah kiri di kaki popup.
    $page->keys('dialog[data-modal="organization-preview"]', 'ArrowRight')->wait(1)
        ->assertSeeIn('#panel-judul', 'Beta Peramban')
        ->assertQueryStringHas('pratinjau', (string) $this->beta->id);
    $page->click('button[aria-label="Kelompok sebelumnya"]')->wait(1)
        ->assertSeeIn('#panel-judul', 'Alfa Peramban');

    // Tutup menghapus ?pratinjau lewat replaceState: tidak ada entri riwayat baru (tidak bergantung pada history.back()).
    $page->click('dialog[data-modal="organization-preview"] footer button:has-text("Tutup")')->wait(0.5);
    assertPopupClosed($page, 'organization-preview');
    $page->assertPathIs('/admin/kelompok')->assertQueryStringMissing('pratinjau')
        ->assertScript('window.history.length', $history)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('pratinjau dibuka langsung dari URL bisa ditutup dengan X, Tutup, Esc, dan klik latar, lalu dibuka lagi', function (string $close) {
    $dialog = 'dialog[data-modal="organization-preview"]';
    $page = visit('/admin/kelompok?pratinjau='.$this->gamma->id)->wait(0.5);
    assertPopupOpen($page, 'organization-preview');
    $page->assertSeeIn('#panel-judul', 'Gamma Peramban');

    match ($close) {
        'X' => $page->click($dialog.' button[aria-label="Tutup"]'),
        'Tutup' => $page->click($dialog.' footer button:has-text("Tutup")'),
        'Esc' => $page->keys($dialog, 'Escape'),
        // Klik di <dialog> itu sendiri (latar di luar kotak popup), di pojok kiri atas layar.
        'latar' => $page->script("document.querySelector('{$dialog}').dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 2, clientY: 2 }))"),
    };
    $page->wait(0.5);

    assertPopupClosed($page, 'organization-preview');
    $page->assertPathIs('/admin/kelompok')->assertQueryStringMissing('pratinjau');

    // Setelah ditutup, klik baris membuka popup lagi.
    $page->click('a[data-row-link]:has-text("Alfa Peramban")')->wait(1);
    assertPopupOpen($page, 'organization-preview');
    $page->assertSeeIn('#panel-judul', 'Alfa Peramban')
        ->assertQueryStringHas('pratinjau', (string) $this->alpha->id)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['X', 'Tutup', 'Esc', 'latar']);

test('pratinjau: tombol Buka detail membuka halaman detail; ?pratinjau=ID langsung membuka popup', function () {
    $page = visit('/admin/kelompok?pratinjau='.$this->gamma->id)->wait(0.5);
    assertPopupOpen($page, 'organization-preview');
    $page->assertSeeIn('#panel-judul', 'Gamma Peramban')
        ->click('dialog[data-modal="organization-preview"] footer a:has-text("Buka detail")')
        ->wait(1)
        ->assertPathIs('/admin/kelompok/'.$this->gamma->id)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('klik di dalam popup pratinjau tidak memicu klik baris di belakangnya', function () {
    $page = visit('/admin/kelompok');
    $page->click('a[data-row-link]:has-text("Alfa Peramban")')->wait(1);
    $page->click('#panel-judul')->wait(0.5)
        ->assertPathIs('/admin/kelompok')
        ->assertSeeIn('#panel-judul', 'Alfa Peramban');
    assertPopupOpen($page, 'organization-preview');
    $page->assertNoJavaScriptErrors();
});

test('OPD: pratinjau hanya kelompok binaan; tab Verifikasi profil juga membuka popup', function () {
    $this->actingAs(User::factory()->opd($this->opd)->create());
    $this->alpha->update(['profile_status' => App\Enums\ProfileStatus::Unverified]);

    $page = visit('/opd/kelompok')->assertDontSee('Gamma Peramban');
    $page->click('a[data-row-link]:has-text("Alfa Peramban")')->wait(1);
    assertPopupOpen($page, 'organization-preview');

    $queue = visit('/opd/kelompok/verifikasi-profil?jenis=baru');
    $queue->click('a[data-row-link]:has-text("Alfa Peramban")')->wait(1);
    assertPopupOpen($queue, 'organization-preview');
    $queue->assertPathIs('/opd/kelompok/verifikasi-profil')->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});
