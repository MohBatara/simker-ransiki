<?php

use App\Enums\ProfileStatus;
use App\Models\BusinessSector;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ProfileNeedsReview;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->sector = BusinessSector::factory()->create();
    $this->organization = Organization::factory()->public()->create(['name' => 'KUB Contoh Verifikasi', 'leader_name' => 'Ketua Lama']);
    $this->organization->sectors()->attach($this->sector);
    $this->organization->opds()->attach($this->opd);
    $this->kelompok = User::factory()->kelompok($this->organization)->create();
    $this->opdUser = User::factory()->opd($this->opd)->create();
    $this->profile = fn (array $overrides = []) => [
        'sectors' => [$this->sector->id], 'legal_status' => 'unknown', 'leader_name' => 'Ketua Lama', ...$overrides,
    ];
});

test('OPD terkait menandai profil terverifikasi (siapa, kapan); OPD lain 404, kelompok 403', function () {
    $this->actingAs(User::factory()->opd()->create())
        ->post("/opd/kelompok/{$this->organization->id}/verifikasi-profil")->assertNotFound();
    $this->actingAs($this->kelompok)
        ->post("/opd/kelompok/{$this->organization->id}/verifikasi-profil")->assertForbidden();

    $this->actingAs($this->opdUser)
        ->post("/opd/kelompok/{$this->organization->id}/verifikasi-profil")
        ->assertSessionHas('status', 'Profil ditandai terverifikasi.');

    expect($this->organization->fresh())
        ->profile_status->toBe(ProfileStatus::Verified)
        ->profile_verified_by->toBe($this->opdUser->id)
        ->profile_verified_at->not->toBeNull();

    $this->get("/opd/kelompok/{$this->organization->id}")->assertSee('Profil terverifikasi')->assertSee($this->opdUser->name);
});

test('kelompok mengubah field penting: perlu verifikasi ulang, masuk antrean, OPD terkait diberi notifikasi', function () {
    Notification::fake();
    $this->organization->forceFill(['profile_status' => ProfileStatus::Verified, 'profile_verified_at' => now()])->save();
    $otherOpdUser = User::factory()->opd()->create();

    $this->actingAs($this->kelompok)->put('/kelompok/profil', ($this->profile)(['leader_name' => 'Ketua Baru', 'phone' => '0812']));

    $organization = $this->organization->fresh();
    expect($organization->profile_status)->toBe(ProfileStatus::NeedsReview)
        ->and($organization->profile_changed_fields)->toBe(['leader_name', 'phone']);
    Notification::assertSentTo($this->opdUser, ProfileNeedsReview::class);
    Notification::assertNotSentTo($otherOpdUser, ProfileNeedsReview::class);

    $this->actingAs($this->opdUser)->get('/opd/verifikasi-profil')
        ->assertOk()->assertSee('KUB Contoh Verifikasi')->assertSee('Ketua, Telepon');
    $this->actingAs($otherOpdUser)->get('/opd/verifikasi-profil')->assertOk()->assertDontSee('KUB Contoh Verifikasi');
    $this->actingAs(User::factory()->admin()->create())->get('/admin/verifikasi-profil')->assertOk()->assertSee('KUB Contoh Verifikasi');

    // Verifikasi ulang mengosongkan daftar perubahan dan mengeluarkannya dari antrean.
    $this->actingAs($this->opdUser)->post("/opd/kelompok/{$this->organization->id}/verifikasi-profil");
    expect($this->organization->fresh())->profile_status->toBe(ProfileStatus::Verified)->profile_changed_fields->toBeNull();
    $this->get('/opd/verifikasi-profil')->assertDontSee('KUB Contoh Verifikasi');
});

test('bidang usaha termasuk field penting', function () {
    $this->organization->forceFill(['profile_status' => ProfileStatus::Verified])->save();
    $newSector = BusinessSector::factory()->create();

    $this->actingAs($this->kelompok)->put('/kelompok/profil', ($this->profile)(['sectors' => [$newSector->id]]));

    expect($this->organization->fresh())->profile_status->toBe(ProfileStatus::NeedsReview)->profile_changed_fields->toBe(['sectors']);
});

test('field lain tidak memicu verifikasi ulang, begitu pula perubahan oleh OPD/Admin', function () {
    Notification::fake();
    $this->organization->forceFill(['profile_status' => ProfileStatus::Verified])->save();

    $this->actingAs($this->kelompok)->put('/kelompok/profil', ($this->profile)(['markets' => 'Pasar Manokwari', 'members_count' => 30, 'assets' => 'Gudang']));
    expect($this->organization->fresh()->profile_status)->toBe(ProfileStatus::Verified);

    $this->actingAs($this->opdUser)->put("/opd/kelompok/{$this->organization->id}", ($this->profile)(['leader_name' => 'Ketua dari OPD']));
    expect($this->organization->fresh())->leader_name->toBe('Ketua dari OPD')->profile_status->toBe(ProfileStatus::Verified);

    Notification::assertNothingSent();
});

test('profil yang belum pernah diverifikasi tidak masuk antrean saat diubah', function () {
    $this->actingAs($this->kelompok)->put('/kelompok/profil', ($this->profile)(['leader_name' => 'Ketua Baru']));

    expect($this->organization->fresh()->profile_status)->toBe(ProfileStatus::Unverified);
});

test('badge "Profil diverifikasi OPD" di website publik hanya untuk profil terverifikasi', function () {
    $this->get('/direktori/'.$this->organization->slug)->assertOk()->assertDontSee('Profil diverifikasi OPD');

    $this->organization->forceFill(['profile_status' => ProfileStatus::Verified])->save();
    $this->get('/direktori/'.$this->organization->slug)->assertOk()->assertSee('Profil diverifikasi OPD');
    $this->get('/direktori')->assertSee('Profil diverifikasi OPD');

    // Perlu verifikasi ulang: badge hilang, tetapi kelompok tetap tampil publik.
    $this->organization->forceFill(['profile_status' => ProfileStatus::NeedsReview])->save();
    $this->get('/direktori/'.$this->organization->slug)->assertOk()->assertDontSee('Profil diverifikasi OPD');
});

test('notifikasi verifikasi ulang membuka tab Riwayat kelompok', function () {
    $this->organization->forceFill(['profile_status' => ProfileStatus::Verified])->save();
    $this->actingAs($this->kelompok)->put('/kelompok/profil', ($this->profile)(['leader_name' => 'Ketua Baru']));

    $notification = $this->opdUser->notifications()->sole();
    $this->actingAs($this->opdUser)->get('/notifikasi')->assertSee('Profil KUB Contoh Verifikasi diubah kelompok dan perlu verifikasi ulang (Ketua).');
    $this->get("/notifikasi/{$notification->id}")->assertRedirect("/opd/kelompok/{$this->organization->id}/riwayat");
});
