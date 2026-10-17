<?php

use App\Models\Opd;
use App\Models\SiteSetting;

test('kontak cepat, kantor distrik, OPD pembina, dan keperluan tampil berurutan', function () {
    SiteSetting::put([
        'office_address' => "Jalan Contoh 1\nRansiki",
        'office_hours' => 'Senin sampai Jumat',
        'office_phone' => '0800-1234-5678',
        'office_whatsapp' => '0812-0000-1111',
        'office_email' => 'kantor@contoh.test',
    ]);
    Opd::create(['name' => 'Dinas Contoh', 'short_name' => 'Contoh', 'description' => 'Keterangan OPD contoh.']);

    $html = $this->get('/kontak')->assertOk()->getContent();
    preg_match('#<ul[^>]*aria-label="Kontak cepat".*?</ul>#s', $html, $quick);

    expect($quick[0])->toContain('tel:080012345678')->toContain('https://wa.me/6281200001111')->toContain('mailto:kantor@contoh.test')
        ->and($html)->toContain('Jalan Contoh 1<br />')
        // Alamat sudah memuat "Ransiki": tidak ditambah "Distrik Ransiki" lagi.
        ->toContain('query=Jalan%20Contoh%201%20Ransiki"')
        ->toContain('Keterangan OPD contoh.')->toContain('href="'.route('password.request').'"')
        ->and(strip_tags($html))->toMatch('/Hubungi kami.*Kantor Distrik Ransiki.*OPD pembina.*Dinas Contoh\s+Contoh.*Ada keperluan\?/s');
});

test('isian kontak kosong tidak ditampilkan', function () {
    $html = $this->get('/kontak')->assertOk()->getContent();

    expect($html)->not->toContain('aria-label="Kontak cepat"')->not->toContain('google.com/maps')
        ->toContain('Alamat dan jam layanan kantor sedang disiapkan.')
        ->toContain('Daftar OPD sedang disiapkan.');
});
