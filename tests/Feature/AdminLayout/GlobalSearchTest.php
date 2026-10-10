<?php

use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\Village;

beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->village = Village::factory()->create(['name' => 'Kampung Contoh Pesisir']);
    $this->ours = Organization::factory()->create(['name' => 'KUB Maju Bersama', 'leader_name' => 'Ketua Yohanis', 'village_id' => $this->village->id]);
    $this->ours->opds()->attach($this->opd);
    $this->foreign = Organization::factory()->create(['name' => 'KUB Maju Lain', 'leader_name' => 'Ketua Yohanis Dua']);
    Product::factory()->for($this->ours)->create(['name' => 'Kopi Robusta Contoh']);
    Product::factory()->for($this->foreign)->create(['name' => 'Kopi Arabika Lain']);
});

test('cari menurut nama kelompok, nama ketua, kampung, dan produk', function (string $query, string $expected) {
    $this->actingAs(User::factory()->admin()->create())->get('/admin/cari?q='.urlencode($query))
        ->assertOk()
        ->assertSee($expected);
})->with([
    ['Maju Bersama', 'KUB Maju Bersama'],
    ['Yohanis', 'Ketua: Ketua Yohanis'],
    ['Pesisir', 'KUB Maju Bersama'],
    ['Robusta', 'Kopi Robusta Contoh'],
]);

test('OPD hanya mendapat hasil dari kelompok binaan', function () {
    $this->actingAs(User::factory()->opd($this->opd)->create())->get('/opd/cari?q=Maju')
        ->assertOk()
        ->assertSee('KUB Maju Bersama')
        ->assertDontSee('KUB Maju Lain');

    $this->get('/opd/cari?q=Kopi')->assertSee('Kopi Robusta Contoh')->assertDontSee('Kopi Arabika Lain');
});

test('kata terlalu pendek, tanpa hasil, dan karakter khusus', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/cari?q=K')->assertSee('Ketik paling sedikit 2 huruf.');
    $this->get('/admin/cari?q=tidakada')->assertSee('Tidak ada hasil untuk');
    // % tidak dianggap wildcard.
    $this->get('/admin/cari?q=%25%25')->assertSee('Tidak ada hasil untuk');
});

test('kotak cari global ada di bilah atas Admin dan OPD, tidak untuk kelompok', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertSee('action="'.route('admin.search').'"', false);
    $this->actingAs(User::factory()->opd($this->opd)->create())->get('/opd')->assertSee('action="'.route('opd.search').'"', false);
    $this->actingAs(User::factory()->kelompok($this->ours)->create())->get('/kelompok/cari')->assertNotFound();
});
