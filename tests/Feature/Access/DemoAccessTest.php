<?php

use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
});

function demoUser(string $username): User
{
    return User::where('username', $username)->firstOrFail();
}

test('scope visibleTo memberi jumlah kelompok sesuai peran', function () {
    $kelompok1 = demoUser('kelompok1');

    expect(Organization::visibleTo(demoUser('admin'))->count())->toBe(40)
        ->and(Organization::visibleTo(demoUser('opd.perikanan'))->count())->toBe(9)
        ->and(Organization::visibleTo($kelompok1)->pluck('id')->all())->toBe([$kelompok1->organization_id]);
});

test('scope dan Policy selalu sejalan untuk setiap akun dan setiap kelompok', function () {
    $organizations = Organization::all();

    foreach (User::all() as $user) {
        $visible = Organization::visibleTo($user)->pluck('id')->all();

        foreach ($organizations as $organization) {
            expect($user->can('view', $organization))->toBe(in_array($organization->id, $visible), "{$user->username} / {$organization->name}");
        }
    }
});

test('kelompok1 tidak bisa membuka laporan kelompok2 lewat ID di URL', function () {
    $own = MonthlyReport::where('organization_id', demoUser('kelompok1')->organization_id)->firstOrFail();
    $other = MonthlyReport::where('organization_id', demoUser('kelompok2')->organization_id)->firstOrFail();

    $this->actingAs(demoUser('kelompok1'));
    $this->get("/kelompok/laporan/{$own->id}")->assertOk();
    $this->get("/kelompok/laporan/{$other->id}")->assertNotFound()->assertSee('Halaman tidak ditemukan');
    $this->get('/kelompok/laporan/999999')->assertNotFound();
});

test('OPD tidak bisa membuka kelompok dan laporan yang bukan binaannya', function () {
    $organizationId = demoUser('kelompok1')->organization_id;
    $report = MonthlyReport::where('organization_id', $organizationId)->firstOrFail();

    $this->actingAs(demoUser('opd.perikanan'));
    $this->get("/opd/kelompok/{$organizationId}")->assertNotFound();
    $this->get("/opd/laporan/{$report->id}")->assertNotFound();

    $this->actingAs(demoUser('opd.pertanian'));
    $this->get("/opd/kelompok/{$organizationId}")->assertOk()->assertSee('Ubah profil');
    $this->get("/opd/laporan/{$report->id}")->assertOk();
});

test('OPD boleh mengubah profil kelompok binaan, tidak untuk non-binaan', function () {
    $organization = Organization::findOrFail(demoUser('kelompok1')->organization_id);

    expect(demoUser('opd.pertanian')->can('update', $organization))->toBeTrue()
        ->and(demoUser('opd.perikanan')->can('update', $organization))->toBeFalse();

    $this->actingAs(demoUser('opd.pertanian'))->get("/opd/kelompok/{$organization->id}/ubah")->assertOk();
    $this->actingAs(demoUser('opd.perikanan'))->get("/opd/kelompok/{$organization->id}/ubah")->assertNotFound();
});

test('akun uji dapat login dengan kata sandi password', function (string $username, string $dashboard) {
    $this->post('/login', ['login' => $username, 'password' => 'password'])->assertRedirect(route($dashboard));
})->with([
    ['admin', 'admin.dashboard'],
    ['opd.dinkopdag', 'opd.dashboard'],
    ['kelompok2', 'kelompok.dashboard'],
]);
