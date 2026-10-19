<?php

use App\Enums\OrganizationStatus;
use App\Enums\ProfileStatus;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Support\Navigation;

test('label status verifikasi profil satu sumber dan jelas', function () {
    expect(ProfileStatus::Verified->label())->toBe('Profil terverifikasi')
        ->and(ProfileStatus::NeedsReview->label())->toBe('Perubahan menunggu verifikasi')
        ->and(ProfileStatus::Unverified->label())->toBe('Profil belum pernah diverifikasi');
});

test('tab Verifikasi profil: perubahan menunggu dulu, lalu belum pernah diverifikasi; terverifikasi dan ditutup tidak masuk', function () {
    $opd = Opd::factory()->create();
    $make = function (string $name, ProfileStatus $status, OrganizationStatus $state = OrganizationStatus::Active) use ($opd) {
        $organization = Organization::factory()->create(['name' => $name, 'status' => $state, 'profile_status' => $status, 'profile_changed_at' => $status === ProfileStatus::NeedsReview ? now() : null]);
        $organization->opds()->attach($opd);

        return $organization;
    };
    $make('KUB Belum Pernah', ProfileStatus::Unverified);
    $make('KUB Berubah', ProfileStatus::NeedsReview);
    $make('KUB Sudah', ProfileStatus::Verified);
    $make('KUB Tutup', ProfileStatus::Unverified, OrganizationStatus::Closed);
    $user = User::factory()->opd($opd)->create();

    $response = $this->actingAs($user)->get('/opd/kelompok/verifikasi-profil')->assertOk()
        ->assertSeeInOrder(['KUB Berubah', 'Perubahan menunggu verifikasi', 'KUB Belum Pernah', 'Profil belum pernah diverifikasi'])
        ->assertSee('Tandai terverifikasi')
        ->assertDontSee('KUB Sudah')
        ->assertDontSee('KUB Tutup');

    // Hitungan tab, badge menu, dan isi tab SAMA.
    expect($response->viewData('organizations')->total())->toBe(2)
        ->and(Organization::profileReviewQueue($user)->count())->toBe(2)
        ->and(Navigation::counts($user)['profiles'])->toBe(2);
});

test('detail kelompok yang belum pernah diverifikasi: pil status dan tombol Tandai terverifikasi bagi yang berhak', function () {
    $opd = Opd::factory()->create();
    $organization = Organization::factory()->create(['profile_status' => ProfileStatus::Unverified]);
    $organization->opds()->attach($opd);

    $this->actingAs(User::factory()->opd($opd)->create())->get("/opd/kelompok/{$organization->id}")->assertOk()
        ->assertSeeInOrder(['aria-label="Status kelompok"', 'Profil belum pernah diverifikasi', 'Tandai terverifikasi'], false);

    // Kelompok sendiri melihat statusnya tanpa tombol.
    $this->actingAs(User::factory()->kelompok($organization)->create())->get('/kelompok/profil')->assertOk()
        ->assertSee('Profil belum pernah diverifikasi')
        ->assertDontSee('Tandai terverifikasi');
});
