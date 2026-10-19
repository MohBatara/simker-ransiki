<?php

use App\Enums\OrganizationStatus;
use App\Enums\ProfileStatus;
use App\Models\ChangeLog;
use App\Models\InstitutionType;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Models\Village;
use App\Support\Navigation;

beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->make = function (string $name, ProfileStatus $status, OrganizationStatus $state = OrganizationStatus::Active) {
        $organization = Organization::factory()->create(['name' => $name, 'status' => $state, 'profile_status' => $status, 'profile_changed_at' => $status === ProfileStatus::NeedsReview ? now() : null]);
        $organization->opds()->attach($this->opd);

        return $organization;
    };
    $this->user = User::factory()->opd($this->opd)->create();
});

test('label status verifikasi profil satu sumber dan jelas', function () {
    expect(ProfileStatus::Verified->label())->toBe('Profil terverifikasi')
        ->and(ProfileStatus::NeedsReview->label())->toBe('Perubahan menunggu verifikasi')
        ->and(ProfileStatus::Unverified->label())->toBe('Profil belum pernah diverifikasi');
});

test('badge menu hanya menghitung perubahan menunggu; angka tab = total keduanya', function () {
    ($this->make)('KUB Belum Pernah', ProfileStatus::Unverified);
    ($this->make)('KUB Belum Pernah Dua', ProfileStatus::Unverified);
    ($this->make)('KUB Berubah', ProfileStatus::NeedsReview);
    ($this->make)('KUB Sudah', ProfileStatus::Verified);
    ($this->make)('KUB Tutup', ProfileStatus::NeedsReview, OrganizationStatus::Closed);

    expect(Navigation::counts($this->user))->toMatchArray(['profiles' => 1, 'profiles_new' => 2, 'profile_queue' => 3])
        ->and(Organization::profileReviewQueue($this->user)->count())->toBe(3);

    $html = $this->actingAs($this->user)->get('/opd/kelompok')->assertOk()->getContent();
    expect($html)->toContain('aria-label="Kelompok binaan, 1 menunggu verifikasi"');
    preg_match('#<nav x-ref="scroller"[^>]*>(.*?)</nav>#s', $html, $tabs);
    expect($tabs[1])->toMatch('#Verifikasi profil\s*<span[^>]*>3</span>#');
});

test('badge menu tidak tampil bila tidak ada perubahan menunggu, walau ada profil yang belum pernah diverifikasi', function () {
    ($this->make)('KUB Belum Pernah', ProfileStatus::Unverified);

    $this->actingAs($this->user)->get('/opd/kelompok')->assertOk()
        ->assertDontSee('aria-label="Kelompok binaan, 1 menunggu verifikasi"', false)
        ->assertSee('aria-label="Kelompok binaan"', false);
});

test('chip filter: bawaan perubahan bila ada, kolom mengikuti jenis, pil tidak diulang di tiap baris', function () {
    ($this->make)('KUB Belum Pernah', ProfileStatus::Unverified);
    ($this->make)('KUB Berubah', ProfileStatus::NeedsReview);
    $this->actingAs($this->user);

    $this->get('/opd/kelompok/verifikasi-profil')->assertOk()
        ->assertSeeText('Perubahan menunggu (1)')
        ->assertSeeText('Belum pernah diverifikasi (1)')
        ->assertSee('KUB Berubah')->assertDontSee('KUB Belum Pernah')
        ->assertSee('Yang perlu diperiksa')->assertDontSee('Ditambahkan')
        ->assertDontSee('Perubahan menunggu verifikasi</span>', false)
        ->assertSee('Tandai terverifikasi');

    $this->get('/opd/kelompok/verifikasi-profil?jenis=baru')->assertOk()
        ->assertSee('KUB Belum Pernah')->assertDontSee('KUB Berubah')
        ->assertSeeInOrder(['Kelompok', 'Kampung', 'Ditambahkan'])
        ->assertDontSee('Yang perlu diperiksa');
});

test('chip filter: tanpa perubahan menunggu, bawaan Belum pernah diverifikasi', function () {
    ($this->make)('KUB Belum Pernah', ProfileStatus::Unverified);

    $this->actingAs($this->user)->get('/opd/kelompok/verifikasi-profil')->assertOk()
        ->assertSee('KUB Belum Pernah')
        ->assertSee('Ditambahkan');
    expect($this->get('/opd/kelompok/verifikasi-profil?jenis=salah')->viewData('kind'))->toBe('baru');
});

test('dasbor: perubahan menunggu dan profil yang belum pernah diverifikasi menjadi dua tugas terpisah', function () {
    ($this->make)('KUB Belum Pernah', ProfileStatus::Unverified);
    ($this->make)('KUB Belum Pernah Dua', ProfileStatus::Unverified);
    ($this->make)('KUB Berubah', ProfileStatus::NeedsReview);

    $this->actingAs($this->user)->get('/opd')->assertOk()
        ->assertSee('1 perubahan profil menunggu verifikasi')
        ->assertSee('Periksa 2 profil yang belum pernah diverifikasi')
        ->assertSee('/opd/kelompok/verifikasi-profil?jenis=baru', false);
});

test('kelompok yang dibuat Admin langsung terverifikasi dan tercatat di Riwayat; tidak masuk antrean', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Contoh']);

    $this->actingAs($admin)->post('/admin/kelompok', [
        'name' => 'KUB Baru Dibuat Admin',
        'institution_type_id' => InstitutionType::factory()->create()->id,
        'village_id' => Village::factory()->create()->id,
        'sectors' => [App\Models\BusinessSector::factory()->create()->id],
        'opds' => [$this->opd->id],
        'status' => 'active',
        'legal_status' => 'unknown',
        'reporting_month' => 9,
        'reporting_year' => 2026,
    ])->assertRedirect();

    $organization = Organization::where('name', 'KUB Baru Dibuat Admin')->firstOrFail();
    expect($organization->profile_status)->toBe(ProfileStatus::Verified)
        ->and($organization->profile_verified_by)->toBe($admin->id)
        ->and($organization->profile_verified_at)->not->toBeNull()
        ->and(Organization::profileReviewQueue($admin)->whereKey($organization->id)->exists())->toBeFalse();

    $log = ChangeLog::where('organization_id', $organization->id)->where('field', 'profile_status')->firstOrFail();
    expect($log->new_value)->toBe('Diverifikasi otomatis saat dibuat oleh Admin Contoh');

    $this->get("/admin/kelompok/{$organization->id}/riwayat")->assertOk()
        ->assertSee('Verifikasi profil')
        ->assertSee('Diverifikasi otomatis saat dibuat oleh Admin Contoh');
});

test('detail kelompok yang belum pernah diverifikasi: pil status dan tombol Tandai terverifikasi bagi yang berhak', function () {
    $organization = ($this->make)('KUB Lama', ProfileStatus::Unverified);

    $this->actingAs($this->user)->get("/opd/kelompok/{$organization->id}")->assertOk()
        ->assertSeeInOrder(['aria-label="Status kelompok"', 'Profil belum pernah diverifikasi', 'Tandai terverifikasi'], false);

    $this->actingAs(User::factory()->kelompok($organization)->create())->get('/kelompok/profil')->assertOk()
        ->assertSee('Profil belum pernah diverifikasi')
        ->assertDontSee('Tandai terverifikasi');
});
