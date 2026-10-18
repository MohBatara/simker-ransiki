<?php

use App\Models\BusinessSector;
use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->sector = BusinessSector::factory()->create();
    $this->binaan = Organization::factory()->create(['name' => 'KUB Contoh Binaan']);
    $this->binaan->opds()->attach($this->opd);
    $this->binaan->sectors()->attach($this->sector);
    $this->bukan = Organization::factory()->create(['name' => 'KUB Contoh Bukan Binaan']);
    $this->bukan->opds()->attach(Opd::factory()->create());
    $this->actingAs(User::factory()->opd($this->opd)->create());
});

test('daftar kelompok binaan hanya berisi kelompok yang dibina OPD itu', function () {
    $this->get('/opd/kelompok')
        ->assertOk()
        ->assertSee('Kelompok binaan')
        ->assertSee('KUB Contoh Binaan')
        ->assertDontSee('KUB Contoh Bukan Binaan')
        ->assertDontSee('name="opd"', false);

    $this->get('/opd/kelompok?q=tidak-ada')->assertSee('Tidak ada kelompok yang cocok');
});

test('semua halaman kelompok binaan bisa dibuka, non-binaan 404', function (string $path) {
    $license = License::factory()->for($this->binaan)->create();
    $otherLicense = License::factory()->for($this->bukan)->create();
    MonthlyReport::factory()->for($this->binaan)->create();

    $this->get('/opd/kelompok/'.$this->binaan->id.str_replace('{izin}', $license->id, $path))->assertOk();
    $this->get('/opd/kelompok/'.$this->bukan->id.str_replace('{izin}', $otherLicense->id, $path))->assertNotFound();
})->with(['', '/produk', '/izin', '/izin/{izin}', '/laporan', '/ubah']);

test('OPD tidak punya tab akun pengguna dan tidak bisa menghapus kelompok', function () {
    $this->get("/opd/kelompok/{$this->binaan->id}")->assertDontSee('Akun Pengguna')->assertDontSee('Tutup atau hapus kelompok');
    $this->get("/opd/kelompok/{$this->binaan->id}/akun")->assertNotFound();
    $this->delete("/admin/kelompok/{$this->binaan->id}")->assertForbidden();
});

test('OPD mengubah profil kelompok binaan termasuk bidang usaha, tanpa kolom khusus admin', function () {
    $newSector = BusinessSector::factory()->create();

    $this->put("/opd/kelompok/{$this->binaan->id}", [
        'name' => 'Nama Diganti', 'opds' => [], 'sectors' => [$newSector->id], 'legal_status' => 'in_process', 'markets' => 'Pasar contoh',
    ])->assertRedirect("/opd/kelompok/{$this->binaan->id}");

    $organization = $this->binaan->fresh();
    expect($organization->name)->toBe('KUB Contoh Binaan')
        ->and($organization->markets)->toBe('Pasar contoh')
        ->and($organization->opds()->pluck('opds.id')->all())->toBe([$this->opd->id])
        ->and($organization->sectors()->pluck('business_sectors.id')->all())->toBe([$newSector->id]);

    $this->put("/opd/kelompok/{$this->bukan->id}", ['sectors' => [$newSector->id], 'legal_status' => 'none'])->assertNotFound();
});

test('OPD mengelola produk dan izin kelompok binaan, bukan kelompok lain', function () {
    $this->post("/opd/kelompok/{$this->binaan->id}/produk", ['name' => 'Ikan asap'])->assertSessionHas('status', 'Produk ditambahkan.');
    $this->post("/opd/kelompok/{$this->binaan->id}/izin", ['type' => 'NIB', 'status' => 'none'])->assertSessionHas('status', 'Izin ditambahkan.');
    $this->post("/opd/kelompok/{$this->bukan->id}/produk", ['name' => 'Produk Asing'])->assertNotFound();

    $foreign = Product::factory()->for($this->bukan)->create();
    $this->put("/opd/kelompok/{$this->bukan->id}/produk/{$foreign->id}", ['name' => 'Ganti'])->assertNotFound();
    // Produk kelompok lain lewat URL kelompok binaan juga ditolak.
    $this->put("/opd/kelompok/{$this->binaan->id}/produk/{$foreign->id}", ['name' => 'Ganti'])->assertNotFound();

    expect(Product::where('name', 'Ikan asap')->value('organization_id'))->toBe($this->binaan->id)
        ->and(Product::where('name', 'Produk Asing')->exists())->toBeFalse()
        ->and($foreign->fresh()->name)->not->toBe('Ganti');
});
