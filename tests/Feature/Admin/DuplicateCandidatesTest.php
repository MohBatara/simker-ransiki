<?php

use App\Enums\OrganizationStatus;
use App\Models\BusinessSector;
use App\Models\DuplicateDismissal;
use App\Models\InstitutionType;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Models\Village;
use App\Support\DuplicateCandidates;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->village = Village::factory()->create(['name' => 'Kampung Contoh 1']);
    $this->otherVillage = Village::factory()->create(['name' => 'Kampung Contoh 2']);
    $this->payload = fn (array $overrides = []) => [
        'name' => 'KUB Contoh Kakao',
        'institution_type_id' => InstitutionType::factory()->create()->id,
        'village_id' => $this->village->id,
        'sectors' => [BusinessSector::factory()->create()->id],
        'opds' => [Opd::factory()->create()->id],
        'status' => 'active',
        'legal_status' => 'unknown',
        'reporting_month' => 9,
        'reporting_year' => 2026,
        ...$overrides,
    ];
});

test('kandidat mirip: kampung sama = peringatan kuat dan didahulukan, kampung lain = ringan', function () {
    $far = Organization::factory()->create(['name' => 'Kelompok Tani Harapan Jaya', 'village_id' => $this->otherVillage->id]);
    $near = Organization::factory()->create(['name' => 'Kel. Tani Harapan Jaja', 'village_id' => $this->village->id]);
    Organization::factory()->create(['name' => 'Kelompok Nelayan Harapan Jaya', 'village_id' => $this->village->id]);
    Organization::factory()->create(['name' => 'KUB Sagu Lestari', 'village_id' => $this->village->id]);

    $candidates = (new DuplicateCandidates)->for('kelompok tani  harapan-jaya', $this->village->id);

    expect($candidates->pluck('id')->all())->toBe([$near->id, $far->id])
        ->and($candidates->pluck('strong')->all())->toBe([true, false])
        ->and($candidates[0])->toMatchArray(['village' => 'Kampung Contoh 1', 'status' => null]);
});

test('ambang kemiripan diatur lewat config', function () {
    Organization::factory()->create(['name' => 'KUB Sagu Mas', 'village_id' => $this->village->id]);

    config(['simker.duplicates.min_similarity' => 85]);
    expect((new DuplicateCandidates)->for('KUB Sagu Emas', $this->village->id))->toHaveCount(1);

    config(['simker.duplicates.min_similarity' => 99]);
    expect((new DuplicateCandidates)->for('KUB Sagu Emas', $this->village->id))->toHaveCount(0);
});

test('peringatan tidak memblokir: centang konfirmasi tetap menyimpan kelompok', function () {
    Organization::factory()->create(['name' => 'Kelompok Tani Maju', 'village_id' => $this->village->id]);
    $this->actingAs($this->admin);

    $this->post('/admin/kelompok', ($this->payload)(['name' => 'Kel Tani Maju']))->assertSessionHas('duplicates');
    expect(Organization::count())->toBe(1);

    $this->post('/admin/kelompok', ($this->payload)(['name' => 'Kel Tani Maju', 'confirm_similar' => '1']))->assertSessionHasNoErrors();
    expect(Organization::where('name', 'Kel Tani Maju')->exists())->toBeTrue();
});

test('form menampilkan kandidat dengan jenis, kampung, dan tautan', function () {
    $other = Organization::factory()->create(['name' => 'Kelompok Tani Maju', 'village_id' => $this->village->id]);
    $this->actingAs($this->admin);

    $this->from('/admin/kelompok/tambah')->post('/admin/kelompok', ($this->payload)(['name' => 'Kel Tani Maju']));

    $this->get('/admin/kelompok/tambah')
        ->assertSee('Nama mirip ditemukan di kampung yang sama')
        ->assertSee($other->institutionType->name)
        ->assertSee('Kampung Contoh 1 · kampung sama')
        ->assertSee("/admin/kelompok/{$other->id}")
        ->assertSee('Saya sudah memeriksa, tetap simpan');
});

test('halaman kemungkinan data ganda: pasangan tampil, "Bukan data ganda" tidak muncul lagi', function () {
    $first = Organization::factory()->create(['name' => 'KUB Sagu Lestari', 'village_id' => $this->village->id]);
    $second = Organization::factory()->create(['name' => 'Koperasi Sagu Lestari', 'village_id' => $this->village->id]);
    $third = Organization::factory()->create(['name' => 'Kelompok Sagu Lestari', 'village_id' => $this->otherVillage->id]);
    Organization::factory()->create(['name' => 'KUB Kakao Jaya', 'village_id' => $this->village->id]);
    $this->actingAs($this->admin);

    $pairs = $this->get('/admin/kelompok/data-ganda')->assertOk()
        ->assertSee('Kemungkinan data ganda')
        ->assertSee('Kampung sama')
        ->assertSee("/admin/kelompok/{$first->id}")
        ->viewData('pairs');
    expect($pairs->total())->toBe(3)
        ->and($pairs->first()['strong'])->toBeTrue();

    $this->from('/admin/kelompok/data-ganda')
        ->post('/admin/kelompok/data-ganda/bukan-ganda', ['first' => $second->id, 'second' => $first->id])
        ->assertRedirect('/admin/kelompok/data-ganda');
    // Dua kali tidak membuat baris ganda.
    $this->post('/admin/kelompok/data-ganda/bukan-ganda', ['first' => $first->id, 'second' => $second->id]);

    expect(DuplicateDismissal::count())->toBe(1)
        ->and(DuplicateDismissal::first()->only(['organization_id', 'other_organization_id']))->toBe(['organization_id' => $first->id, 'other_organization_id' => $second->id]);

    $remaining = collect($this->get('/admin/kelompok/data-ganda')->viewData('pairs')->items())
        ->map(fn ($pair) => [$pair['first']['id'], $pair['second']['id']])->all();
    expect($remaining)->not->toContain([$first->id, $second->id])->toHaveCount(2);

    // Saat mengubah kelompok, pasangan yang sudah ditandai tidak diperingatkan lagi.
    expect((new DuplicateCandidates)->for($second->name, $this->village->id, $second->id)->pluck('id')->all())->toBe([$third->id]);
});

test('pasangan yang salah satunya sudah ditutup tidak tampil di halaman data ganda', function () {
    Organization::factory()->create(['name' => 'KUB Sagu Lestari', 'village_id' => $this->village->id]);
    Organization::factory()->create(['name' => 'Koperasi Sagu Lestari', 'village_id' => $this->village->id, 'status' => OrganizationStatus::Closed]);

    $this->actingAs($this->admin)->get('/admin/kelompok/data-ganda')->assertOk()->assertSee('Tidak ada kemungkinan data ganda');
});

test('halaman data ganda khusus admin', function (string $role) {
    $organization = Organization::factory()->create();
    $user = $role === 'opd' ? User::factory()->opd()->create() : User::factory()->kelompok($organization)->create();
    $this->actingAs($user);

    $this->get('/admin/kelompok/data-ganda')->assertForbidden();
    $this->post('/admin/kelompok/data-ganda/bukan-ganda', ['first' => $organization->id, 'second' => Organization::factory()->create()->id])->assertForbidden();
    expect(DuplicateDismissal::count())->toBe(0);
})->with(['opd', 'kelompok']);
