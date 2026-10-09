<?php

use App\Enums\OrganizationStatus;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Models\Village;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->type = InstitutionType::factory()->create(['name' => 'KUB']);
    $this->village = Village::factory()->create(['name' => 'Kampung Contoh 1']);
    $this->sector = BusinessSector::factory()->create(['name' => 'Perkebunan']);
    $this->opd = Opd::factory()->create(['name' => 'Dinas Contoh', 'short_name' => 'Contoh']);
});

function organizationPayload(object $test, array $overrides = []): array
{
    return [
        'name' => 'KUB Contoh Kakao',
        'institution_type_id' => $test->type->id,
        'village_id' => $test->village->id,
        'sectors' => [$test->sector->id],
        'opds' => [$test->opd->id],
        'status' => 'active',
        'legal_status' => 'unknown',
        'reporting_month' => 9,
        'reporting_year' => 2026,
        ...$overrides,
    ];
}

test('hanya admin yang bisa membuka pengelolaan kelompok', function (string $role) {
    $organization = Organization::factory()->create();
    $this->actingAs($role === 'opd' ? User::factory()->opd()->create() : User::factory()->create());

    $this->get('/admin/kelompok')->assertForbidden();
    $this->get('/admin/kelompok/tambah')->assertForbidden();
    $this->get("/admin/kelompok/{$organization->id}")->assertForbidden();
    $this->post('/admin/kelompok', organizationPayload($this))->assertForbidden();
    $this->delete("/admin/kelompok/{$organization->id}")->assertForbidden();
})->with(['opd', 'kelompok']);

test('daftar menampilkan kelompok beserta jenis, kampung, dan status', function () {
    Organization::factory()->create(['name' => 'KUB Contoh Kakao', 'institution_type_id' => $this->type->id, 'village_id' => $this->village->id]);

    $this->actingAs($this->admin)
        ->get('/admin/kelompok')
        ->assertOk()
        ->assertSee('KUB Contoh Kakao')
        ->assertSee('KUB · Kampung Contoh 1')
        ->assertSee('Belum ada OPD pembina')
        ->assertSee('Menampilkan 1–1 dari 1 kelompok');
});

test('filter daftar kelompok', function (string $query, array $visible) {
    $otherVillage = Village::factory()->create();
    $otherSector = BusinessSector::factory()->create();
    $a = Organization::factory()->create(['name' => 'Kelompok Contoh Alfa', 'village_id' => $this->village->id, 'institution_type_id' => $this->type->id]);
    $a->sectors()->attach([$this->sector->id, $otherSector->id]);
    $a->opds()->attach($this->opd);
    $b = Organization::factory()->closed()->create(['name' => 'Kelompok Contoh Beta', 'village_id' => $otherVillage->id]);
    $b->sectors()->attach($otherSector);

    $query = strtr($query, [
        '{village}' => $this->village->id, '{type}' => $this->type->id,
        '{sector}' => $this->sector->id, '{opd}' => $this->opd->id,
    ]);
    $response = $this->actingAs($this->admin)->get('/admin/kelompok?'.$query)->assertOk();

    foreach (['Kelompok Contoh Alfa', 'Kelompok Contoh Beta'] as $name) {
        in_array($name, $visible) ? $response->assertSee($name) : $response->assertDontSee($name);
    }
    // Kelompok dengan dua bidang usaha tidak muncul ganda.
    expect(substr_count($response->getContent(), 'Kelompok Contoh Alfa'))->toBeLessThanOrEqual(1);
})->with([
    'nama' => ['q=alfa', ['Kelompok Contoh Alfa']],
    'kampung' => ['village={village}', ['Kelompok Contoh Alfa']],
    'jenis' => ['type={type}', ['Kelompok Contoh Alfa']],
    'bidang usaha' => ['sector={sector}', ['Kelompok Contoh Alfa']],
    'OPD' => ['opd={opd}', ['Kelompok Contoh Alfa']],
    'tanpa OPD' => ['opd=none', ['Kelompok Contoh Beta']],
    'status' => ['status=closed', ['Kelompok Contoh Beta']],
    'gabungan tanpa hasil' => ['status=closed&village={village}', []],
]);

test('filter tetap terbawa di tautan halaman berikutnya', function () {
    Organization::factory()->count(21)->create(['village_id' => $this->village->id]);

    $this->actingAs($this->admin)
        ->get("/admin/kelompok?village={$this->village->id}")
        ->assertSee("village={$this->village->id}&amp;page=2", false);
});

test('admin menambah kelompok lengkap dengan bidang usaha dan OPD', function () {
    $this->actingAs($this->admin)
        ->post('/admin/kelompok', organizationPayload($this, ['members_count' => '', 'workforce_count' => '0']))
        ->assertSessionHasNoErrors();

    $organization = Organization::where('name', 'KUB Contoh Kakao')->firstOrFail();

    expect($organization->reporting_starts_on->toDateString())->toBe('2026-09-01')
        ->and($organization->members_count)->toBeNull()
        ->and($organization->workforce_count)->toBe(0)
        ->and($organization->created_by)->toBe($this->admin->id)
        ->and($organization->updated_by)->toBe($this->admin->id)
        ->and($organization->sectors()->pluck('business_sectors.id')->all())->toBe([$this->sector->id])
        ->and($organization->opds()->pluck('opds.id')->all())->toBe([$this->opd->id]);
});

test('validasi form kelompok berbahasa Indonesia', function (array $overrides, string $field, string $message) {
    $this->actingAs($this->admin)
        ->post('/admin/kelompok', organizationPayload($this, $overrides))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'nama kosong' => [['name' => ''], 'name', 'Nama kelompok wajib diisi.'],
    'tanpa bidang usaha' => [['sectors' => []], 'sectors', 'Pilih minimal satu bidang usaha.'],
    'anggota negatif' => [['members_count' => -1], 'members_count', 'Jumlah anggota paling kecil 0.'],
    'email salah' => [['email' => 'bukan-email'], 'email', 'Email harus berupa alamat email yang valid.'],
    'kampung tidak ada' => [['village_id' => 999999], 'village_id', 'Pilihan kampung tidak valid.'],
]);

test('nama mirip di kampung yang sama perlu dikonfirmasi sebelum disimpan', function () {
    Organization::factory()->create(['name' => 'Kelompok Tani Maju', 'village_id' => $this->village->id]);
    $this->actingAs($this->admin);

    $this->from('/admin/kelompok/tambah')
        ->post('/admin/kelompok', organizationPayload($this, ['name' => 'Kel. Tani Maju']))
        ->assertRedirect('/admin/kelompok/tambah')
        ->assertSessionHas('similar');
    expect(Organization::count())->toBe(1);

    $this->get('/admin/kelompok/tambah')->assertSee('Nama mirip ditemukan')->assertSee('Kelompok Tani Maju');

    $this->post('/admin/kelompok', organizationPayload($this, ['name' => 'Kel. Tani Maju', 'confirm_similar' => '1']))
        ->assertSessionHasNoErrors();
    expect(Organization::count())->toBe(2);
});

test('nama mirip di kampung lain tidak memicu peringatan', function () {
    Organization::factory()->create(['name' => 'Kelompok Tani Maju']);

    $this->actingAs($this->admin)
        ->post('/admin/kelompok', organizationPayload($this, ['name' => 'Kelompok Tani Maju']))
        ->assertSessionMissing('similar');

    expect(Organization::count())->toBe(2);
});

test('mengubah nama menjadi mirip tetap disimpan lalu diberi peringatan', function () {
    Organization::factory()->create(['name' => 'Kelompok Tani Maju', 'village_id' => $this->village->id]);
    $organization = Organization::factory()->create(['name' => 'KUB Lain', 'village_id' => $this->village->id]);

    $this->actingAs($this->admin)
        ->put("/admin/kelompok/{$organization->id}", organizationPayload($this, ['name' => 'Kelompok Tani Maju']))
        ->assertRedirect("/admin/kelompok/{$organization->id}")
        ->assertSessionHas('similar', fn ($similar) => count($similar) === 1);

    expect($organization->fresh()->name)->toBe('Kelompok Tani Maju');
});

test('admin melepas semua OPD terkait', function () {
    $organization = Organization::factory()->create(['village_id' => $this->village->id]);
    $organization->opds()->attach($this->opd);

    $payload = organizationPayload($this, ['name' => $organization->name]);
    unset($payload['opds']);

    $this->actingAs($this->admin)->put("/admin/kelompok/{$organization->id}", $payload)->assertSessionHasNoErrors();

    expect($organization->opds()->count())->toBe(0);
});

test('kolom khusus admin diabaikan bila dikirim lewat form bersama oleh pengguna lain', function () {
    $organization = Organization::factory()->create(['name' => 'Nama Asli', 'village_id' => $this->village->id]);
    $organization->opds()->attach($this->opd);
    $opdUser = User::factory()->opd($this->opd)->create();

    $request = App\Http\Requests\Organizations\OrganizationRequest::create('/', 'PUT', organizationPayload($this, [
        'name' => 'Nama Diganti', 'status' => 'closed', 'is_public' => '1', 'opds' => [], 'leader_name' => 'Ketua Baru',
    ]));
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $opdUser);
    $request->setRouteResolver(fn () => tap(new Illuminate\Routing\Route('PUT', '/', []), fn ($route) => $route->bind($request)->setParameter('organization', $organization)));
    $request->validateResolved();

    $data = $request->organizationData();

    expect($data)->toHaveKeys(['leader_name', 'sectors'])
        ->and($data)->not->toHaveKeys(['name', 'status', 'is_public', 'opds', 'reporting_starts_on', 'village_id']);
});

test('kelompok tanpa laporan dan akun bisa dihapus', function () {
    $organization = Organization::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/kelompok/{$organization->id}")
        ->assertRedirect('/admin/kelompok')
        ->assertSessionHas('status', 'Kelompok dihapus.');

    expect($organization->fresh())->toBeNull();
});

test('kelompok yang punya laporan tidak bisa dihapus dan ditawari ditutup', function () {
    $organization = Organization::factory()->create();
    MonthlyReport::factory()->for($organization)->create();
    $this->actingAs($this->admin);

    $this->get("/admin/kelompok/{$organization->id}")
        ->assertSee('punya 1 laporan, jadi tidak bisa dihapus')
        ->assertSee('Tandai ditutup')
        ->assertDontSee('Hapus kelompok');

    $this->delete("/admin/kelompok/{$organization->id}")
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'tidak bisa dihapus'));
    expect($organization->fresh())->not->toBeNull();

    $this->patch("/admin/kelompok/{$organization->id}/status", ['status' => 'closed'])
        ->assertSessionHas('status', 'Status kelompok diubah menjadi ditutup.');
    expect($organization->fresh()->status)->toBe(OrganizationStatus::Closed);
});

test('ID kelompok yang tidak ada 404', function () {
    $this->actingAs($this->admin)->get('/admin/kelompok/999999')->assertNotFound();
});
