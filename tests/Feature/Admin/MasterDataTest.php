<?php

use App\Enums\MasterDataType;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Models\Village;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('hanya admin yang bisa membuka dan mengubah data master', function (string $role) {
    $village = Village::factory()->create();
    $this->actingAs($role === 'opd' ? User::factory()->opd()->create() : User::factory()->create());

    $this->get('/admin/pengaturan/data-master/kampung')->assertForbidden();
    $this->post('/admin/pengaturan/data-master/kampung', ['name' => 'Kampung Baru'])->assertForbidden();
    $this->put("/admin/pengaturan/data-master/kampung/{$village->id}", ['name' => 'Ganti'])->assertForbidden();
    $this->delete("/admin/pengaturan/data-master/kampung/{$village->id}")->assertForbidden();

    expect(Village::where('name', 'Kampung Baru')->exists())->toBeFalse()
        ->and($village->fresh())->not->toBeNull();
})->with(['opd', 'kelompok']);

test('tamu diarahkan ke halaman login', function () {
    $this->get('/admin/pengaturan/data-master/kampung')->assertRedirect('/login');
});

test('menu Pengaturan memuat tab Data master yang mengarah ke daftar kampung', function () {
    $this->actingAs($this->admin)->get('/admin/pengaturan/pengguna')->assertSee('aria-label="Pengaturan"', false)
        ->assertSee('href="'.route('admin.master.index').'"', false);
    $this->get('/admin/pengaturan/data-master')->assertRedirect('/admin/pengaturan/data-master/kampung');
});

test('keempat jenis data master memakai halaman yang sama', function (MasterDataType $type) {
    $record = $type->modelClass()::factory()->create();

    $this->actingAs($this->admin)
        ->get("/admin/pengaturan/data-master/{$type->value}")
        ->assertOk()
        ->assertSee($record->name)
        ->assertSee('Tambah '.$type->noun());
})->with(MasterDataType::cases());

test('jenis data master yang tidak dikenal 404', function () {
    $this->actingAs($this->admin)->get('/admin/pengaturan/data-master/tidak-ada')->assertNotFound();
});

test('pencarian menyaring daftar', function () {
    Village::factory()->create(['name' => 'Kampung Contoh Pantai']);
    Village::factory()->create(['name' => 'Kampung Contoh Gunung']);

    $this->actingAs($this->admin)
        ->get('/admin/pengaturan/data-master/kampung?q=pantai')
        ->assertSee('Kampung Contoh Pantai')
        ->assertDontSee('Kampung Contoh Gunung');

    $this->get('/admin/pengaturan/data-master/kampung?q=tidakada')->assertSee('Tidak ada hasil');
});

test('jumlah kelompok pemakai tampil dan menggantikan tombol hapus', function () {
    $used = Village::factory()->create(['name' => 'Kampung Dipakai']);
    Organization::factory()->count(2)->create(['village_id' => $used->id]);
    Village::factory()->create(['name' => 'Kampung Kosong']);

    $this->actingAs($this->admin)
        ->get('/admin/pengaturan/data-master/kampung')
        ->assertSeeInOrder(['Kampung Dipakai', 'dipakai 2 kelompok', 'Kampung Kosong', 'Hapus']);
});

test('kelompok dengan banyak bidang usaha dihitung sekali per bidang', function () {
    $sector = BusinessSector::factory()->create(['name' => 'Bidang Contoh']);
    $other = BusinessSector::factory()->create();
    Organization::factory()->create()->sectors()->attach([$sector->id, $other->id]);

    $this->actingAs($this->admin)->get('/admin/pengaturan/data-master/bidang-usaha')->assertSee('dipakai 1 kelompok');
});

test('admin menambah data master', function () {
    $this->actingAs($this->admin)
        ->post('/admin/pengaturan/data-master/kampung', ['name' => '  Kampung Contoh Baru  '])
        ->assertRedirect()
        ->assertSessionHas('status', 'Kampung ditambahkan.');

    expect(Village::where('name', 'Kampung Contoh Baru')->exists())->toBeTrue();
});

test('admin menambah OPD dengan nama singkat dan keterangan opsional', function () {
    $this->actingAs($this->admin)
        ->post('/admin/pengaturan/data-master/opd', ['name' => 'Dinas Contoh', 'short_name' => 'Contoh', 'description' => ''])
        ->assertSessionHasNoErrors();

    expect(Opd::where('short_name', 'Contoh')->value('description'))->toBeNull();
});

test('nama wajib diisi dan harus unik tanpa membedakan huruf besar', function () {
    Village::factory()->create(['name' => 'Kampung Contoh 1']);
    $this->actingAs($this->admin);

    $this->post('/admin/pengaturan/data-master/kampung', ['name' => '', '_modal' => 'record-form'])
        ->assertSessionHasErrors(['name' => 'Nama kampung wajib diisi.']);
    $this->post('/admin/pengaturan/data-master/kampung', ['name' => 'kampung contoh 1'])
        ->assertSessionHasErrors(['name' => 'Nama kampung sudah dipakai.']);

    expect(Village::count())->toBe(1);
});

test('form gagal validasi terbuka kembali dengan isian sebelumnya', function () {
    $this->actingAs($this->admin)
        ->from('/admin/pengaturan/data-master/kampung')
        ->post('/admin/pengaturan/data-master/kampung', ['name' => '', '_modal' => 'record-form'])
        ->assertRedirect('/admin/pengaturan/data-master/kampung');

    $this->get('/admin/pengaturan/data-master/kampung')
        ->assertSee('data-modal="record-form" data-open-on-load', false)
        ->assertSee('Nama kampung wajib diisi.');
});

test('kode jenis kelembagaan unik dan berformat huruf kecil', function () {
    InstitutionType::factory()->create(['code' => 'bumdes']);
    $this->actingAs($this->admin);

    $this->post('/admin/pengaturan/data-master/jenis-kelembagaan', ['name' => 'Jenis Baru', 'code' => 'bumdes'])
        ->assertSessionHasErrors(['code' => 'Kode sudah dipakai.']);
    $this->post('/admin/pengaturan/data-master/jenis-kelembagaan', ['name' => 'Jenis Baru', 'code' => 'Kode Baru'])
        ->assertSessionHasErrors(['code' => 'Format kode tidak valid.']);
});

test('admin mengubah data tanpa bentrok dengan namanya sendiri', function () {
    $village = Village::factory()->create(['name' => 'Kampung Lama']);
    Village::factory()->create(['name' => 'Kampung Lain']);
    $this->actingAs($this->admin);

    $this->put("/admin/pengaturan/data-master/kampung/{$village->id}", ['name' => 'Kampung Lama'])->assertSessionHasNoErrors();
    $this->put("/admin/pengaturan/data-master/kampung/{$village->id}", ['name' => 'Kampung Lain'])
        ->assertSessionHasErrors(['name' => 'Nama kampung sudah dipakai.']);
    $this->put("/admin/pengaturan/data-master/kampung/{$village->id}", ['name' => 'Kampung Baru'])
        ->assertSessionHas('status', 'Kampung diperbarui.');

    expect($village->fresh()->name)->toBe('Kampung Baru');
});

test('data yang belum dipakai bisa dihapus', function () {
    $village = Village::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/pengaturan/data-master/kampung/{$village->id}")
        ->assertSessionHas('status', 'Kampung dihapus.');

    expect($village->fresh())->toBeNull();
});

test('data yang dipakai kelompok tidak bisa dihapus walau dipaksa lewat URL', function (MasterDataType $type, Closure $attach) {
    $record = $type->modelClass()::factory()->create();
    $attach($record);

    $this->actingAs($this->admin)
        ->delete("/admin/pengaturan/data-master/{$type->value}/{$record->id}")
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'masih dipakai 1 kelompok'));

    expect($record->fresh())->not->toBeNull();
})->with([
    'kampung' => [MasterDataType::Village, fn ($village) => Organization::factory()->create(['village_id' => $village->id])],
    'jenis kelembagaan' => [MasterDataType::InstitutionType, fn ($type) => Organization::factory()->create(['institution_type_id' => $type->id])],
    'bidang usaha' => [MasterDataType::BusinessSector, fn ($sector) => Organization::factory()->create()->sectors()->attach($sector)],
    'OPD' => [MasterDataType::Opd, fn ($opd) => Organization::factory()->create()->opds()->attach($opd)],
]);

test('OPD yang masih punya akun tidak bisa dihapus', function () {
    $opd = Opd::factory()->create();
    User::factory()->opd($opd)->create();

    $this->actingAs($this->admin)
        ->delete("/admin/pengaturan/data-master/opd/{$opd->id}")
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'masih dipakai 1 akun'));

    expect($opd->fresh())->not->toBeNull();
});

test('data yang tidak ada 404', function () {
    $this->actingAs($this->admin)->delete('/admin/pengaturan/data-master/kampung/999999')->assertNotFound();
});
