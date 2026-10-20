<?php

use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

/*
 * Halaman Produk akun Kelompok di HP (390 px, iPhone 14): kaki kartu berisi tombol Ubah, Foto, Hapus yang langsung terlihat
 * (min 44 px, garis pemisah selebar kartu), Hapus meminta konfirmasi lewat popup. Di desktop menu titik tiga rata kanan
 * pada tombolnya dan isinya terlihat. Jalankan per file setelah `sail npm run build`.
 */
beforeEach(function () {
    $this->organization = Organization::factory()->create(['name' => 'KUB Kartu Peramban']);
    $this->product = Product::factory()->for($this->organization)->create(['name' => 'Keripik Kartu']);
    $this->actingAs(User::factory()->kelompok($this->organization)->create());
});

test('HP 390 px: tombol aksi langsung terlihat di kaki kartu, Ubah dan Hapus membuka popup', function () {
    $page = visit('/kelompok/produk');
    $page->resize(390, 844)->wait(0.3);

    $page->assertScript(<<<'JS'
        () => {
            const td = document.querySelector('td:has([data-phone-actions])');
            const card = td.closest('tr').getBoundingClientRect();
            const line = td.getBoundingClientRect();
            const buttons = [...td.querySelectorAll('[data-phone-actions] button')];
            return Math.abs(line.left - card.left) <= 2 && Math.abs(line.right - card.right) <= 2
                && buttons.map((b) => b.innerText.trim()).join('/') === 'Ubah/Foto/Hapus'
                && buttons.every((b) => b.getBoundingClientRect().height >= 44)
                && ! document.querySelector('button[aria-label="Tindakan untuk Keripik Kartu"]').offsetParent
                && document.documentElement.scrollWidth === innerWidth;
        }
        JS, true);

    $page->click('[data-phone-actions] button:has-text("Hapus")')->wait(0.4);
    assertPopupOpen($page, 'record-delete');
    $page->assertSeeIn('dialog[data-modal="record-delete"]', 'Keripik Kartu')
        ->click('dialog[data-modal="record-delete"] footer button:has-text("Batal")')->wait(0.4);
    assertPopupClosed($page, 'record-delete');

    $page->click('[data-phone-actions] button:has-text("Ubah")')->wait(0.4);
    assertPopupOpen($page, 'record-form');
    $page->assertValue('dialog[data-modal="record-form"] input[name="name"]', 'Keripik Kartu')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();

    expect(Product::whereKey($this->product->id)->exists())->toBeTrue();
});

test('desktop: menu titik tiga rata kanan pada tombolnya, di dalam layar, pilihannya terlihat', function () {
    $page = visit('/kelompok/produk');
    $page->resize(1280, 800)->wait(0.3);

    $page->click('button[aria-label="Tindakan untuk Keripik Kartu"]')->wait(0.3);
    $page->assertScript(<<<'JS'
        () => {
            const button = document.querySelector('button[aria-label="Tindakan untuk Keripik Kartu"]').getBoundingClientRect();
            const menu = [...document.querySelectorAll('[x-show="open"]')].find((m) => getComputedStyle(m).display !== 'none');
            const box = menu.getBoundingClientRect();
            const first = menu.querySelector('button').getBoundingClientRect();
            return Math.abs(box.right - button.right) <= 1 && box.left >= 0 && box.right <= innerWidth
                && first.left >= box.left && first.width > 100
                && menu.innerText.includes('Ubah') && menu.innerText.includes('Hapus')
                && ! document.querySelector('[data-phone-actions]').offsetParent;
        }
        JS, true)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});
