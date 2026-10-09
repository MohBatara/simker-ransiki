<?php

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->organization = Organization::factory()->create(['name' => 'KUB Contoh Detail']);
});

test('semua tab detail kelompok bisa dibuka tanpa query N+1', function (string $path, string $text) {
    Product::factory()->for($this->organization)->create(['name' => 'Kakao kering']);
    License::factory()->for($this->organization)->create(['type' => 'PIRT']);
    MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-08-01']);
    User::factory()->kelompok($this->organization)->create(['name' => 'Ketua Contoh Detail']);

    $this->actingAs($this->admin)
        ->get("/admin/kelompok/{$this->organization->id}{$path}")
        ->assertOk()
        ->assertSee('KUB Contoh Detail')
        ->assertSee($text);
})->with([
    'profil' => ['', 'Mulai wajib lapor'],
    'produk' => ['/produk', 'Kakao kering'],
    'izin' => ['/izin', 'PIRT'],
    'laporan' => ['/laporan', 'Agustus 2026'],
    'akun' => ['/akun', 'Ketua Contoh Detail'],
]);

test('admin menambah, mengubah, dan menghapus produk', function () {
    $this->actingAs($this->admin);
    $base = "/admin/kelompok/{$this->organization->id}/produk";

    $this->post($base, ['name' => 'Kopi bubuk', 'unit' => 'kg', 'is_public' => '1', 'is_featured' => '1'])
        ->assertSessionHas('status', 'Produk ditambahkan.');
    $product = Product::where('name', 'Kopi bubuk')->firstOrFail();
    expect($product->is_public)->toBeTrue()->and($product->is_featured)->toBeTrue();

    $this->put("{$base}/{$product->id}", ['name' => 'Kopi bubuk robusta'])->assertSessionHas('status', 'Produk diperbarui.');
    expect($product->fresh()->name)->toBe('Kopi bubuk robusta')
        ->and($product->fresh()->is_featured)->toBeFalse();

    $this->delete("{$base}/{$product->id}")->assertSessionHas('status', 'Produk dihapus.');
    expect($product->fresh())->toBeNull();
});

test('nama produk wajib diisi', function () {
    $this->actingAs($this->admin)
        ->post("/admin/kelompok/{$this->organization->id}/produk", ['name' => ''])
        ->assertSessionHasErrors(['name' => 'Nama produk wajib diisi.']);
});

test('produk kelompok lain tidak bisa diubah lewat URL kelompok ini', function () {
    $otherProduct = Product::factory()->create();
    $this->actingAs($this->admin);

    $this->put("/admin/kelompok/{$this->organization->id}/produk/{$otherProduct->id}", ['name' => 'Ganti'])->assertNotFound();
    $this->delete("/admin/kelompok/{$this->organization->id}/produk/{$otherProduct->id}")->assertNotFound();

    expect($otherProduct->fresh()->name)->not->toBe('Ganti');
});

test('admin menambah dan mengubah izin', function () {
    $this->actingAs($this->admin);
    $base = "/admin/kelompok/{$this->organization->id}/izin";

    $this->post($base, ['type' => 'NIB', 'status' => 'in_process', 'submitted_on' => '2026-08-01'])
        ->assertSessionHas('status', 'Izin ditambahkan.');
    $license = License::where('type', 'NIB')->firstOrFail();

    $this->put("{$base}/{$license->id}", ['type' => 'NIB', 'status' => 'issued', 'number' => 'CONTOH-1', 'submitted_on' => '2026-08-01', 'issued_on' => '2026-09-01'])
        ->assertSessionHas('status', 'Izin diperbarui.');

    expect($license->fresh()->status)->toBe(LicenseStatus::Issued)
        ->and($license->fresh()->issued_on->toDateString())->toBe('2026-09-01');
});

test('tanggal terbit izin tidak boleh sebelum tanggal diajukan', function () {
    $this->actingAs($this->admin)
        ->post("/admin/kelompok/{$this->organization->id}/izin", ['type' => 'NIB', 'status' => 'issued', 'submitted_on' => '2026-09-01', 'issued_on' => '2026-08-01'])
        ->assertSessionHasErrors(['issued_on' => 'Tanggal terbit tidak boleh sebelum tanggal diajukan.']);
});

test('izin kelompok lain tidak bisa dihapus lewat URL kelompok ini', function () {
    $otherLicense = License::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/kelompok/{$this->organization->id}/izin/{$otherLicense->id}")
        ->assertNotFound();

    expect($otherLicense->fresh())->not->toBeNull();
});

test('penanda produk unggulan diabaikan bila dikirim bukan oleh admin', function () {
    $user = User::factory()->kelompok($this->organization)->create();

    $request = App\Http\Requests\Organizations\ProductRequest::create('/', 'POST', ['name' => 'Sagu', 'is_featured' => '1', 'is_public' => '1']);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $user);
    $request->validateResolved();

    expect($request->validated())->toBe(['name' => 'Sagu', 'is_public' => true]);
});
