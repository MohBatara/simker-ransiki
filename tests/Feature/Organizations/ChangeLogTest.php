<?php

use App\Models\BusinessSector;
use App\Models\ChangeLog;
use App\Models\License;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\Village;

beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->sector = BusinessSector::factory()->create(['name' => 'Perkebunan']);
    $this->organization = Organization::factory()->create(['name' => 'KUB Contoh Riwayat', 'leader_name' => 'Ketua Lama', 'markets' => 'Pasar lama']);
    $this->organization->sectors()->attach($this->sector);
    $this->organization->opds()->attach($this->opd);
    $this->kelompok = User::factory()->kelompok($this->organization)->create(['name' => 'Pengurus Contoh']);
    $this->profile = fn (array $overrides = []) => [
        'sectors' => [$this->sector->id], 'legal_status' => 'unknown', 'leader_name' => 'Ketua Lama', 'markets' => 'Pasar lama',
        ...$overrides,
    ];
});

test('perubahan field profil tercatat dengan nilai lama, nilai baru, pengguna, dan waktu', function () {
    $this->actingAs($this->kelompok)->put('/kelompok/profil', ($this->profile)(['leader_name' => 'Ketua Baru']))->assertSessionHasNoErrors();

    $log = ChangeLog::where('field', 'leader_name')->sole();
    expect($log)->organization_id->toBe($this->organization->id)
        ->loggable_type->toBe('organization')
        ->old_value->toBe('Ketua Lama')
        ->new_value->toBe('Ketua Baru')
        ->user_id->toBe($this->kelompok->id)
        ->and($log->created_at)->not->toBeNull()
        // Kolom teknis tidak dicatat.
        ->and(ChangeLog::whereIn('field', ['updated_at', 'updated_by', 'created_at'])->exists())->toBeFalse();
});

test('kampung, bidang usaha, produk, dan izin dicatat sebagai teks siap tampil', function () {
    $village = Village::factory()->create(['name' => 'Kampung Contoh 7']);
    $newSector = BusinessSector::factory()->create(['name' => 'Perikanan']);
    $this->actingAs(User::factory()->admin()->create());

    $this->organization->update(['village_id' => $village->id]);
    expect(ChangeLog::where('field', 'village_id')->sole()->new_value)->toBe('Kampung Contoh 7');

    $this->actingAs($this->kelompok)->put('/kelompok/profil', ($this->profile)(['sectors' => [$newSector->id]]));
    expect(ChangeLog::where('field', 'sectors')->sole())->old_value->toBe('Perkebunan')->new_value->toBe('Perikanan');

    $product = Product::factory()->for($this->organization)->create(['name' => 'Kakao']);
    $product->update(['is_public' => true, 'photo_path' => 'products/x.jpg']);
    $license = License::factory()->for($this->organization)->create(['type' => 'PIRT', 'status' => 'in_process']);
    $license->update(['status' => 'issued']);
    $product->delete();

    expect(ChangeLog::where('subject', 'Produk: Kakao')->pluck('action')->map->value->all())->toBe(['created', 'updated', 'deleted'])
        ->and(ChangeLog::where('field', 'is_public')->sole()->new_value)->toBe('Ya')
        // Path foto (isi file) tidak dicatat.
        ->and(ChangeLog::where('field', 'photo_path')->exists())->toBeFalse()
        ->and(ChangeLog::where('subject', 'Izin: PIRT')->where('field', 'status')->sole())
        ->old_value->toBe('Dalam proses')->new_value->toBe('Terbit');
});

test('tab Riwayat untuk Admin dan OPD terkait; OPD lain 404, kelompok 403', function () {
    $this->actingAs($this->kelompok)->put('/kelompok/profil', ($this->profile)(['leader_name' => 'Ketua Baru']));

    $this->actingAs(User::factory()->admin()->create())
        ->get("/admin/kelompok/{$this->organization->id}/riwayat")
        ->assertOk()->assertSee('Ketua')->assertSee('Ketua Lama')->assertSee('Ketua Baru')->assertSee('Pengurus Contoh');

    $this->actingAs(User::factory()->opd($this->opd)->create())
        ->get("/opd/kelompok/{$this->organization->id}/riwayat")->assertOk()->assertSee('Ketua Baru');

    $this->actingAs(User::factory()->opd()->create())
        ->get("/opd/kelompok/{$this->organization->id}/riwayat")->assertNotFound();

    $this->actingAs($this->kelompok)
        ->get("/opd/kelompok/{$this->organization->id}/riwayat")->assertForbidden();
});
