<?php

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Organization;
use App\Models\User;

/*
 * Halaman Legalitas dan izin akun Kelompok di HP (390 px, iPhone 14): kaki kartu izin berisi tombol Ubah dan Hapus yang
 * langsung terlihat (min 44 px, garis pemisah selebar kartu walau kartu bertautan memberi ruang chevron), Hapus meminta
 * konfirmasi lewat popup. Di desktop menu titik tiga rata kanan pada tombolnya. Jalankan per file setelah `sail npm run build`.
 */
beforeEach(function () {
    $this->organization = Organization::factory()->create(['name' => 'KUB Izin Peramban']);
    $this->license = License::factory()->for($this->organization)->create(['type' => 'PIRT Kartu', 'status' => LicenseStatus::InProcess]);
    $this->actingAs(User::factory()->kelompok($this->organization)->create());
});

test('HP 390 px: tombol Ubah dan Hapus langsung terlihat, Hapus meminta konfirmasi, kartu tidak ikut terbuka', function () {
    $page = visit('/kelompok/izin');
    $page->resize(390, 844)->wait(0.3);

    $page->assertScript(<<<'JS'
        () => {
            const td = document.querySelector('td:has([data-phone-actions])');
            const card = td.closest('tr').getBoundingClientRect();
            const line = td.getBoundingClientRect();
            const buttons = [...td.querySelectorAll('[data-phone-actions] button')];
            return Math.abs(line.left - card.left) <= 2 && Math.abs(line.right - card.right) <= 2
                && buttons.map((b) => b.innerText.trim()).join('/') === 'Ubah/Hapus'
                && buttons.every((b) => b.getBoundingClientRect().height >= 44)
                && document.documentElement.scrollWidth === innerWidth;
        }
        JS, true);

    $page->click('[data-phone-actions] button:has-text("Hapus")')->wait(0.4);
    assertPopupOpen($page, 'record-delete');
    $page->assertPathIs('/kelompok/izin')
        ->click('dialog[data-modal="record-delete"] footer button:has-text("Batal")')->wait(0.4);
    assertPopupClosed($page, 'record-delete');

    $page->click('[data-phone-actions] button:has-text("Ubah")')->wait(0.4);
    assertPopupOpen($page, 'record-form');
    $page->assertPathIs('/kelompok/izin')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();

    expect(License::whereKey($this->license->id)->exists())->toBeTrue();
});

test('desktop: menu titik tiga rata kanan pada tombolnya dan pilihannya terlihat', function () {
    $page = visit('/kelompok/izin');
    $page->resize(1280, 800)->wait(0.3);

    $page->click('button[aria-label="Tindakan untuk PIRT Kartu"]')->wait(0.3);
    $page->assertScript(<<<'JS'
        () => {
            const button = document.querySelector('button[aria-label="Tindakan untuk PIRT Kartu"]').getBoundingClientRect();
            const menu = [...document.querySelectorAll('[x-show="open"]')].find((m) => getComputedStyle(m).display !== 'none');
            const box = menu.getBoundingClientRect();
            return Math.abs(box.right - button.right) <= 1 && box.left >= 0 && box.right <= innerWidth
                && menu.innerText.includes('Ubah') && menu.innerText.includes('Hapus');
        }
        JS, true)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});
