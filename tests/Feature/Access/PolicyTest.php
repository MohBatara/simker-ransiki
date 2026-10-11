<?php

use App\Enums\ReportStatus;
use App\Models\Attachment;
use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Dua kelompok, masing-masing dibina OPD berbeda, dengan akun untuk setiap peran.
 */
function accessScenario(): object
{
    $opd = Opd::factory()->create();
    $otherOpd = Opd::factory()->create();
    $organization = Organization::factory()->create();
    $organization->opds()->attach($opd);
    $otherOrganization = Organization::factory()->create();
    $otherOrganization->opds()->attach($otherOpd);

    return (object) [
        'organization' => $organization,
        'users' => [
            'admin' => User::factory()->admin()->create(),
            'opd' => User::factory()->opd($opd)->create(),
            'opd lain' => User::factory()->opd($otherOpd)->create(),
            'kelompok' => User::factory()->kelompok($organization)->create(),
            'kelompok lain' => User::factory()->kelompok($otherOrganization)->create(),
        ],
    ];
}

/** Hasil Policy diringkas menjadi 'allow', 403, atau 404. */
function decide(User $user, string $ability, mixed $arguments): string|int
{
    $response = Gate::forUser($user)->inspect($ability, $arguments);

    return $response->allowed() ? 'allow' : ($response->status() ?? 403);
}

test('profil kelompok, produk, dan izin: admin, OPD pembina, dan pemilik boleh; lainnya 404', function (string $ability, Closure $target) {
    $scenario = accessScenario();
    $arguments = $target($scenario->organization);

    $results = collect($scenario->users)->map(fn (User $user) => decide($user, $ability, $arguments))->all();

    expect($results)->toBe([
        'admin' => 'allow', 'opd' => 'allow', 'opd lain' => 404, 'kelompok' => 'allow', 'kelompok lain' => 404,
    ]);
})->with([
    'lihat kelompok' => ['view', fn (Organization $organization) => $organization],
    'ubah profil kelompok' => ['update', fn (Organization $organization) => $organization],
    'tambah produk' => ['create', fn (Organization $organization) => [Product::class, $organization]],
    'ubah produk' => ['update', fn (Organization $organization) => Product::factory()->for($organization)->create()],
    'hapus produk' => ['delete', fn (Organization $organization) => Product::factory()->for($organization)->create()],
    'tambah izin' => ['create', fn (Organization $organization) => [License::class, $organization]],
    'ubah izin' => ['update', fn (Organization $organization) => License::factory()->for($organization)->create()],
    'lihat lampiran' => ['view', fn (Organization $organization) => Attachment::factory()->create(['attachable_id' => $organization->id])],
]);

test('status, relasi OPD, buat, dan hapus kelompok hanya admin', function (string $ability) {
    $scenario = accessScenario();
    $arguments = $ability === 'create' ? Organization::class : $scenario->organization;

    $allowed = collect($scenario->users)->filter(fn (User $user) => $user->can($ability, $arguments))->keys()->all();

    expect($allowed)->toBe(['admin']);
})->with(['administer', 'create', 'delete']);

test('membuat laporan: kelompok pemilik, OPD pembina, dan admin (atas nama kelompok), selama belum tutup', function () {
    $scenario = accessScenario();
    $results = collect($scenario->users)->map(fn (User $user) => decide($user, 'create', [MonthlyReport::class, $scenario->organization]))->all();

    expect($results)->toBe([
        'admin' => 'allow', 'opd' => 'allow', 'opd lain' => 404, 'kelompok' => 'allow', 'kelompok lain' => 404,
    ]);

    $scenario->organization->update(['status' => 'closed']);
    foreach (['kelompok', 'opd', 'admin'] as $role) {
        expect(decide($scenario->users[$role], 'create', [MonthlyReport::class, $scenario->organization]))->toBe(403);
    }
});

test('mengubah laporan saat draf atau perlu revisi; draf kelompok yang belum diajukan tetap tertutup bagi peninjau', function (ReportStatus $status, ?string $enteredBy, array $expected) {
    $scenario = accessScenario();
    $report = MonthlyReport::factory()->for($scenario->organization)->status($status)->create([
        'submitted_at' => $status === ReportStatus::Draft ? null : now(),
        'entered_by' => $enteredBy ? $scenario->users[$enteredBy]->id : null,
    ]);

    $results = collect($scenario->users)->map(fn (User $user) => decide($user, 'update', $report))->all();

    expect($results)->toBe($expected);
})->with([
    'draf kelompok' => [ReportStatus::Draft, 'kelompok', ['admin' => 404, 'opd' => 404, 'opd lain' => 404, 'kelompok' => 'allow', 'kelompok lain' => 404]],
    'draf diisi OPD' => [ReportStatus::Draft, 'opd', ['admin' => 'allow', 'opd' => 'allow', 'opd lain' => 404, 'kelompok' => 'allow', 'kelompok lain' => 404]],
    'perlu revisi' => [ReportStatus::Revision, 'kelompok', ['admin' => 'allow', 'opd' => 'allow', 'opd lain' => 404, 'kelompok' => 'allow', 'kelompok lain' => 404]],
    'diajukan' => [ReportStatus::Submitted, 'kelompok', ['admin' => 403, 'opd' => 403, 'opd lain' => 404, 'kelompok' => 403, 'kelompok lain' => 404]],
    'terverifikasi' => [ReportStatus::Verified, 'kelompok', ['admin' => 403, 'opd' => 403, 'opd lain' => 404, 'kelompok' => 403, 'kelompok lain' => 404]],
]);

test('verifikasi dan minta revisi: admin dan OPD pembina, hanya untuk laporan diajukan', function (string $ability) {
    $scenario = accessScenario();
    $submitted = MonthlyReport::factory()->for($scenario->organization)->status(ReportStatus::Submitted)->create();
    $draft = MonthlyReport::factory()->for($scenario->organization)->status(ReportStatus::Draft)->create(['period' => '2026-08-01']);

    expect(collect($scenario->users)->map(fn (User $user) => decide($user, $ability, $submitted))->all())->toBe([
        'admin' => 'allow', 'opd' => 'allow', 'opd lain' => 404, 'kelompok' => 403, 'kelompok lain' => 404,
    ]);
    expect(decide($scenario->users['opd'], $ability, $draft))->toBe(403);
})->with(['verify', 'requestRevision']);

test('lampiran laporan ikut aturan laporan: hanya bisa dihapus saat masih bisa diubah', function () {
    $scenario = accessScenario();
    $report = MonthlyReport::factory()->for($scenario->organization)->status(ReportStatus::Revision)->create(['submitted_at' => now()]);
    $attachment = Attachment::factory()->create(['attachable_type' => 'monthly_report', 'attachable_id' => $report->id]);

    expect(decide($scenario->users['kelompok'], 'delete', $attachment))->toBe('allow')
        ->and(decide($scenario->users['opd'], 'view', $attachment))->toBe('allow')
        ->and(decide($scenario->users['opd lain'], 'view', $attachment))->toBe(404);

    $report->update(['status' => ReportStatus::Verified]);
    expect(decide($scenario->users['kelompok'], 'delete', $attachment->fresh()))->toBe(403);
});

test('hanya admin yang mengelola pengguna, dan tidak bisa menonaktifkan atau menghapus diri sendiri', function () {
    $scenario = accessScenario();
    $admin = $scenario->users['admin'];
    $target = $scenario->users['kelompok'];

    foreach (['viewAny', 'create'] as $ability) {
        expect(collect($scenario->users)->filter(fn (User $user) => $user->can($ability, User::class))->keys()->all())->toBe(['admin']);
    }
    foreach (['update', 'resetPassword', 'deactivate', 'delete'] as $ability) {
        expect($admin->can($ability, $target))->toBeTrue()
            ->and($scenario->users['opd']->can($ability, $target))->toBeFalse();
    }

    expect(Gate::forUser($admin)->inspect('deactivate', $admin)->message())->toBe('Anda tidak bisa menonaktifkan akun sendiri.')
        ->and($admin->can('delete', $admin))->toBeFalse();
});

test('draf yang belum pernah diajukan hanya bisa dibuka kelompok pemiliknya', function () {
    $scenario = accessScenario();
    $draft = MonthlyReport::factory()->for($scenario->organization)->create();
    $attachment = Attachment::factory()->create(['attachable_type' => 'monthly_report', 'attachable_id' => $draft->id]);

    expect(collect($scenario->users)->map(fn (User $user) => decide($user, 'view', $draft))->all())->toBe([
        'admin' => 404, 'opd' => 404, 'opd lain' => 404, 'kelompok' => 'allow', 'kelompok lain' => 404,
    ])->and(decide($scenario->users['opd'], 'view', $attachment))->toBe(404);

    // Setelah pernah diajukan (mis. kini perlu revisi), isinya bisa dibuka peninjau.
    $draft->forceFill(['status' => ReportStatus::Revision, 'submitted_at' => now()])->save();
    expect(decide($scenario->users['opd'], 'view', $draft->fresh()))->toBe('allow')
        ->and(decide($scenario->users['admin'], 'view', $draft->fresh()))->toBe('allow');
});

test('membuka kembali laporan terverifikasi hanya Admin', function () {
    $scenario = accessScenario();
    $verified = MonthlyReport::factory()->for($scenario->organization)->status(ReportStatus::Verified)->create(['submitted_at' => now()]);
    $submitted = MonthlyReport::factory()->for($scenario->organization)->status(ReportStatus::Submitted)->create(['period' => '2026-08-01', 'submitted_at' => now()]);

    expect(collect($scenario->users)->map(fn (User $user) => decide($user, 'reopen', $verified))->all())->toBe([
        'admin' => 'allow', 'opd' => 403, 'opd lain' => 404, 'kelompok' => 403, 'kelompok lain' => 404,
    ])->and(decide($scenario->users['admin'], 'reopen', $submitted))->toBe(403);
});
