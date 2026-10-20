<?php

use App\Models\BusinessSector;
use App\Models\License;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->sector = BusinessSector::factory()->create();
    $this->organization = Organization::factory()->create(['name' => 'KUB Contoh Milik']);
    $this->organization->sectors()->attach($this->sector);
    $this->organization->opds()->attach(Opd::factory()->create());
    $this->user = User::factory()->kelompok($this->organization)->create();
    $this->other = Organization::factory()->create(['name' => 'KUB Contoh Lain']);
    $this->actingAs($this->user);
});

test('kelompok membuka profilnya sendiri dengan kolom khusus admin baca-saja', function () {
    $this->get('/kelompok/profil')->assertOk()->assertSee('KUB Contoh Milik')->assertSee('Dokumen kelompok');

    $this->get('/kelompok/profil/ubah')
        ->assertOk()
        ->assertSee('Diatur oleh Admin distrik.')
        ->assertDontSee('name="name"', false)
        ->assertDontSee('name="opds[]"', false)
        ->assertSee('name="sectors[]"', false);
});

test('kelompok mengubah profil dan bidang usaha, tetapi kolom khusus admin diabaikan', function () {
    $newSector = BusinessSector::factory()->create();

    $this->put('/kelompok/profil', [
        'name' => 'Nama Diganti', 'status' => 'closed', 'is_public' => '1', 'opds' => [],
        'sectors' => [$newSector->id], 'legal_status' => 'none', 'leader_name' => 'Ketua Baru',
    ])->assertRedirect('/kelompok/profil')->assertSessionHas('status', 'Profil kelompok diperbarui.');

    $organization = $this->organization->fresh();
    expect($organization->name)->toBe('KUB Contoh Milik')
        ->and($organization->status->value)->toBe('active')
        ->and($organization->leader_name)->toBe('Ketua Baru')
        ->and($organization->opds()->count())->toBe(1)
        ->and($organization->sectors()->pluck('business_sectors.id')->all())->toBe([$newSector->id]);
});

test('kelompok mengelola produknya sendiri tanpa bisa menandai unggulan', function () {
    $this->get('/kelompok/produk')->assertOk();

    $this->post('/kelompok/produk', ['name' => 'Kopi bubuk', 'is_public' => '1', 'is_featured' => '1'])
        ->assertSessionHas('status', 'Produk ditambahkan.');
    $product = Product::where('name', 'Kopi bubuk')->firstOrFail();
    expect($product->organization_id)->toBe($this->organization->id)
        ->and($product->is_public)->toBeTrue()
        ->and($product->is_featured)->toBeFalse();

    $this->put("/kelompok/produk/{$product->id}", ['name' => 'Kopi robusta'])->assertSessionHas('status', 'Produk diperbarui.');
    $this->delete("/kelompok/produk/{$product->id}")->assertSessionHas('status', 'Produk dihapus.');
    expect($product->fresh())->toBeNull();
});

test('kelompok tidak bisa mengubah produk atau izin kelompok lain lewat ID di URL', function () {
    $product = Product::factory()->for($this->other)->create(['name' => 'Produk Lain']);
    $license = License::factory()->for($this->other)->create();

    $this->put("/kelompok/produk/{$product->id}", ['name' => 'Ganti'])->assertNotFound();
    $this->delete("/kelompok/produk/{$product->id}")->assertNotFound();
    $this->get("/kelompok/izin/{$license->id}")->assertNotFound();
    $this->put("/kelompok/izin/{$license->id}", ['type' => 'NIB', 'status' => 'none'])->assertNotFound();
    $this->delete("/kelompok/izin/{$license->id}")->assertNotFound();

    expect($product->fresh()->name)->toBe('Produk Lain')->and($license->fresh())->not->toBeNull();
});

test('kelompok mengelola izin dan status legalitasnya', function () {
    $this->get('/kelompok/izin')->assertOk()->assertSee('Status legalitas');

    $this->post('/kelompok/izin', ['type' => 'PIRT', 'status' => 'in_process'])->assertSessionHas('status', 'Izin ditambahkan.');
    $license = License::where('type', 'PIRT')->firstOrFail();
    expect($license->organization_id)->toBe($this->organization->id);
    $this->get("/kelompok/izin/{$license->id}")->assertOk()->assertSee('Lampiran izin');

    $this->patch('/kelompok/legalitas', ['legal_status' => 'registered', 'legal_number' => 'CONTOH-99'])
        ->assertSessionHas('status', 'Status legalitas diperbarui.');
    expect($this->organization->fresh()->legal_number)->toBe('CONTOH-99');

    $this->patch('/kelompok/legalitas', ['legal_status' => 'tidak-ada'])
        ->assertSessionHasErrors(['legal_status' => 'Pilihan status hukum tidak valid.']);
});

test('menu kelompok mengarah ke halaman profil, produk, dan izin', function () {
    $this->get('/kelompok')
        ->assertSee('aria-label="Profil kelompok"', false)
        ->assertSee('aria-label="Produk"', false)
        ->assertSee('aria-label="Legalitas dan izin"', false);
});
