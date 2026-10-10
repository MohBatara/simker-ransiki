<?php

use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['reporting_starts_on' => '2026-07-01']);
    $this->actingAs(User::factory()->kelompok($this->organization)->create());
});

test('satuan produk dipilih dari daftar dan disimpan rapi', function () {
    $this->get('/kelompok/produk')->assertOk()->assertSee('Lainnya (tulis sendiri)')->assertSee('unitInput(', false)->assertDontSee('name="unit" x-model', false);

    $this->post('/kelompok/produk', ['name' => 'Kopi bubuk', 'unit' => '  KG '])->assertSessionHas('status', 'Produk ditambahkan.');
    expect(Product::sole()->unit)->toBe('kg');

    // Satuan di luar daftar tetap boleh lewat "Lainnya".
    $this->put('/kelompok/produk/'.Product::sole()->id, ['name' => 'Kopi bubuk', 'unit' => 'Toples 250 g'])->assertSessionHasNoErrors();
    expect(Product::sole()->unit)->toBe('toples 250 g');
});

test('satuan laporan disimpan rapi; data lama tetap terbaca di form', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);
    $report->items()->create(['product_name' => 'Minyak kelapa', 'production_unit' => 'Liter ', 'production_qty' => 5]);

    $this->get("/kelompok/laporan/{$report->id}/ubah")->assertOk()->assertSee('Liter ')->assertSee('Lainnya (tulis sendiri)');

    $this->patchJson("/kelompok/laporan/{$report->id}/draf", [
        'items' => [['product_name' => 'Minyak kelapa', 'production_qty' => '5', 'production_unit' => ' Liter', 'sales_qty' => '2', 'sales_unit' => 'Botol Kecil']],
    ])->assertOk();

    $item = $report->items()->sole();
    expect($item->production_unit)->toBe('liter')->and($item->sales_unit)->toBe('botol kecil');
});

test('satuan yang bukan teks ditolak, bukan error server', function () {
    $report = MonthlyReport::factory()->for($this->organization)->create(['period' => '2026-09-01']);

    $this->patchJson("/kelompok/laporan/{$report->id}/draf", ['items' => [['product_name' => 'Kopi', 'sales_unit' => ['kg']]]])
        ->assertJsonValidationErrors('items.0.sales_unit');
});
