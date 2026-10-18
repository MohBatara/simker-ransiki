<?php

use App\Enums\ReportStatus;
use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->opd = Opd::factory()->create(['name' => 'Dinas Contoh']);
    $this->organization = Organization::factory()->create(['name' => 'KUB Asal Contoh']);
    $this->organization->opds()->attach($this->opd);
    $this->report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => now()]);
    $this->reviewer = User::factory()->opd($this->opd)->create();
});

test('detail laporan dari tab kelompok: remah dan kembali ke tab laporan kelompok', function () {
    $this->actingAs($this->reviewer)->get("/opd/laporan/{$this->report->id}?dari=kelompok")->assertOk()
        ->assertSee('aria-label="Remah"', false)
        ->assertSeeInOrder(['Kelompok binaan', 'KUB Asal Contoh', 'Laporan', 'Laporan September 2026'])
        ->assertSee('href="'.route('opd.organizations.reports', $this->organization).'" class="-ml-2', false);
});

test('asal halaman yang tidak dikenal kembali ke bawaan', function () {
    $this->actingAs($this->reviewer)->get("/opd/laporan/{$this->report->id}?dari=https://contoh.test")->assertOk()
        ->assertSeeInOrder(['Laporan', 'Laporan September 2026'])
        ->assertDontSee('contoh.test');
});

test('setelah verifikasi, asal halaman tetap terbawa', function () {
    $this->actingAs($this->reviewer)
        ->post("/opd/laporan/{$this->report->id}/verifikasi?dari=antrean")
        ->assertRedirect("/opd/laporan/{$this->report->id}?dari=antrean");
});

test('tautan ke detail laporan membawa asal halamannya', function () {
    $this->actingAs($this->reviewer);

    $this->get('/opd/laporan/verifikasi')->assertSee("/opd/laporan/{$this->report->id}?dari=antrean", false);
    $this->get("/opd/kelompok/{$this->organization->id}/laporan")->assertSee("/opd/laporan/{$this->report->id}?dari=kelompok", false);
});

test('detail izin memakai remah', function () {
    $license = License::factory()->for($this->organization)->create(['type' => 'NIB']);

    $this->actingAs(User::factory()->admin()->create())->get("/admin/kelompok/{$this->organization->id}/izin/{$license->id}")->assertOk()
        ->assertSeeInOrder(['Kelompok', 'KUB Asal Contoh', 'Izin', 'NIB']);
});

test('tombol Tambah produk dan izin ada di header halaman', function () {
    $member = User::factory()->kelompok($this->organization)->create();
    $this->actingAs($member);

    $this->get('/kelompok/produk')->assertSee("\$dispatch('product-create')", false)->assertSee('x-on:product-create.window="create()"', false);
    $this->get('/kelompok/izin')->assertSee("\$dispatch('license-create')", false);

    $this->actingAs(User::factory()->admin()->create())
        ->get("/admin/kelompok/{$this->organization->id}/produk")->assertSee("\$dispatch('product-create')", false);
});

test('judul dasbor seragam: keterangan Dasbor di atas nama', function () {
    $this->actingAs($this->reviewer)->get('/opd')
        ->assertSeeInOrder(['>Dasbor</p>', '>Dinas Contoh</h1>'], false);
    $this->actingAs(User::factory()->admin()->create())->get('/admin')
        ->assertSeeInOrder(['>Dasbor</p>', '>Distrik Ransiki</h1>'], false);
});

test('form panjang menampilkan ringkasan error dengan tautan ke isian', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->from('/admin/pengaturan/pengguna/tambah')
        ->followingRedirects()
        ->post('/admin/pengaturan/pengguna', [])
        ->assertSee('data-form-errors', false)
        ->assertSee('isian yang perlu diperbaiki')
        ->assertSee('href="#name"', false);
});
