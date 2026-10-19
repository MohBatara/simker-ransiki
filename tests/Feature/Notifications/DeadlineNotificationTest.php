<?php

use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ReportOverdue;
use App\Notifications\ReportReminder;
use App\Notifications\ReportReviewed;
use App\Support\NotificationText;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->otherOpd = Opd::factory()->create();
    $this->opdUser = User::factory()->opd($this->opd)->create();
    $this->otherOpdUser = User::factory()->opd($this->otherOpd)->create();
    $this->inactiveOpdUser = User::factory()->opd($this->opd)->create(['is_active' => false]);
    $this->admin = User::factory()->admin()->create();

    $this->make = function (string $name, array $opds, array $attributes = []) {
        $organization = Organization::factory()->create(['name' => $name, 'reporting_starts_on' => '2026-07-01', ...$attributes]);
        $organization->opds()->attach($opds);

        return [$organization, User::factory()->kelompok($organization)->create()];
    };
});

afterEach(fn () => Carbon::setTestNow());

test('H-3 tenggat: pengingat ke semua akun kelompok yang belum mengajukan', function () {
    [$late, $lateUser] = ($this->make)('KUB Belum', [$this->opd]);
    $secondUser = User::factory()->kelompok($late)->create();
    [$done, $doneUser] = ($this->make)('KUB Sudah', [$this->opd]);
    MonthlyReport::factory()->for($done)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => '2026-10-02 10:00']);

    Carbon::setTestNow(Carbon::parse('2026-10-07 08:00', 'Asia/Jayapura'));
    $this->artisan('simker:pengingat-laporan')->expectsOutput('Pengingat laporan September 2026 dikirim ke 2 akun dari 1 kelompok.')->assertSuccessful();

    foreach ([$lateUser, $secondUser] as $user) {
        $notification = $user->notifications()->sole();
        expect($notification->type)->toBe(ReportReminder::class)
            ->and($notification->data['stage'])->toBe('akhir')
            ->and(NotificationText::describe($notification)['text'])->toBe('Tenggat laporan September 2026 tinggal 3 hari, yaitu 10 Oktober 2026.');
    }
    expect($doneUser->notifications()->count())->toBe(0)
        ->and($this->opdUser->notifications()->count())->toBe(0);
});

test('H+1 tenggat: kelompok terlambat dan OPD terkait diberi tahu; tanpa OPD ke Admin', function () {
    [$late, $lateUser] = ($this->make)('KUB Terlambat', [$this->opd]);
    $secondUser = User::factory()->kelompok($late)->create();
    [$draft, $draftUser] = ($this->make)('KUB Draf', [$this->opd, $this->otherOpd]);
    MonthlyReport::factory()->for($draft)->create(['period' => '2026-09-01']);
    [, $orphanUser] = ($this->make)('KUB Tanpa OPD', []);
    [$revision, $revisionUser] = ($this->make)('KUB Revisi', [$this->opd]);
    MonthlyReport::factory()->for($revision)->status(ReportStatus::Revision)->create(['period' => '2026-09-01', 'submitted_at' => '2026-10-09 10:00']);
    [, $closedUser] = ($this->make)('KUB Tutup', [$this->opd], ['status' => OrganizationStatus::Closed]);

    Carbon::setTestNow(Carbon::parse('2026-10-11 08:00', 'Asia/Jayapura'));
    $this->artisan('simker:pengingat-laporan')
        ->expectsOutput('Pemberitahuan terlambat September 2026 dikirim ke 8 akun dari 3 kelompok.')
        ->assertSuccessful();

    $about = fn (User $user) => $user->notifications()->where('type', ReportOverdue::class)->get()
        ->pluck('data.organization_name')->sort()->values()->all();

    expect($about($lateUser))->toBe(['KUB Terlambat'])
        ->and($about($secondUser))->toBe(['KUB Terlambat'])
        ->and($about($draftUser))->toBe(['KUB Draf'])
        ->and($about($orphanUser))->toBe(['KUB Tanpa OPD'])
        ->and($about($this->opdUser))->toBe(['KUB Draf', 'KUB Terlambat'])
        ->and($about($this->otherOpdUser))->toBe(['KUB Draf'])
        ->and($about($this->admin))->toBe(['KUB Tanpa OPD'])
        ->and($this->inactiveOpdUser->notifications()->count())->toBe(0)
        ->and($revisionUser->notifications()->count())->toBe(0)
        ->and($closedUser->notifications()->count())->toBe(0);

    expect(NotificationText::describe($lateUser->notifications()->sole())['text'])
        ->toBe('Laporan September 2026 terlambat. Tenggatnya 10 Oktober 2026. Segera ajukan laporan.')
        ->and(NotificationText::describe($this->admin->notifications()->sole())['text'])
        ->toBe('KUB Tanpa OPD belum mengajukan laporan September 2026 sampai tenggat 10 Oktober 2026.');
});

test('tidak ada notifikasi ganda untuk kelompok dan periode yang sama', function () {
    [$late, $lateUser] = ($this->make)('KUB Terlambat', [$this->opd]);

    Carbon::setTestNow(Carbon::parse('2026-10-11 08:00', 'Asia/Jayapura'));
    $this->artisan('simker:pengingat-laporan')->assertSuccessful();
    $this->artisan('simker:pengingat-laporan')->expectsOutput('Tidak ada kelompok terlambat yang perlu diberi tahu.')->assertSuccessful();
    $this->artisan('simker:pengingat-laporan --tahap=terlambat --periode=2026-09')->assertSuccessful();

    Carbon::setTestNow(Carbon::parse('2026-10-07 08:00', 'Asia/Jayapura'));
    $this->artisan('simker:pengingat-laporan --tahap=akhir')->assertSuccessful();
    $this->artisan('simker:pengingat-laporan --tahap=akhir')->expectsOutput('Tidak ada kelompok yang perlu diingatkan.');

    expect($lateUser->notifications()->where('type', ReportOverdue::class)->count())->toBe(1)
        ->and($lateUser->notifications()->where('type', ReportReminder::class)->count())->toBe(1)
        ->and($this->opdUser->notifications()->count())->toBe(1);

    // Akun baru di kelompok yang sama tetap menerima pemberitahuan sekali.
    Carbon::setTestNow(Carbon::parse('2026-10-11 09:00', 'Asia/Jayapura'));
    $newUser = User::factory()->kelompok($late)->create();
    $this->artisan('simker:pengingat-laporan --tahap=terlambat --periode=2026-09')
        ->expectsOutput('Pemberitahuan terlambat September 2026 dikirim ke 1 akun dari 1 kelompok.');
    expect($newUser->notifications()->count())->toBe(1)
        ->and($lateUser->notifications()->where('type', ReportOverdue::class)->count())->toBe(1);
});

test('sebelum tenggat lewat, tahap terlambat tidak mengirim apa pun', function () {
    [, $lateUser] = ($this->make)('KUB Belum', [$this->opd]);

    Carbon::setTestNow(Carbon::parse('2026-10-10 23:59', 'Asia/Jayapura'));
    $this->artisan('simker:pengingat-laporan --tahap=terlambat')->assertSuccessful();

    expect($lateUser->notifications()->count())->toBe(0);
});

test('membuka notifikasi terlambat: kelompok ke daftar laporan, OPD ke tab laporan kelompok', function () {
    [$late, $lateUser] = ($this->make)('KUB Terlambat', [$this->opd]);

    Carbon::setTestNow(Carbon::parse('2026-10-11 08:00', 'Asia/Jayapura'));
    $this->artisan('simker:pengingat-laporan');

    $this->actingAs($lateUser)->get('/notifikasi/'.$lateUser->notifications()->sole()->id)->assertRedirect('/kelompok/laporan');
    $this->actingAs($this->opdUser)->get('/notifikasi/'.$this->opdUser->notifications()->sole()->id)->assertRedirect("/opd/kelompok/{$late->id}/laporan");

    // OPD yang sudah tidak terhubung lagi tidak dibawa ke kelompok itu.
    $late->opds()->detach($this->opd);
    $this->actingAs($this->opdUser->fresh())->get('/notifikasi/'.$this->opdUser->notifications()->sole()->id)->assertRedirect('/notifikasi');
});

test('beberapa akun satu kelompok: semuanya menerima notifikasi hasil tinjauan', function () {
    [$organization, $first] = ($this->make)('KUB Dua Akun', [$this->opd]);
    $second = User::factory()->kelompok($organization)->create();
    $report = MonthlyReport::factory()->for($organization)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => now()]);

    $this->actingAs($this->opdUser)->post("/opd/laporan/{$report->id}/verifikasi")->assertSessionHasNoErrors();

    foreach ([$first, $second] as $user) {
        expect($user->notifications()->where('type', ReportReviewed::class)->count())->toBe(1);
    }
});

test('admin bisa menambah akun kedua dari detail kelompok', function () {
    [$organization] = ($this->make)('KUB Dua Akun', [$this->opd]);

    $this->actingAs($this->admin)->get("/admin/kelompok/{$organization->id}/akun")
        ->assertOk()
        ->assertSee('Tambah akun')
        ->assertSee("/admin/pengaturan/pengguna/tambah?kelompok={$organization->id}", false);

    $this->post('/admin/pengaturan/pengguna', [
        'name' => 'Bendahara Contoh', 'username' => 'bendahara.dua', 'role' => 'kelompok', 'organization_id' => $organization->id,
    ])->assertSessionHasNoErrors();

    expect($organization->users()->count())->toBe(2);
    $this->get("/admin/kelompok/{$organization->id}/akun")->assertSee('Bendahara Contoh')->assertSeeInOrder(['Akun', '<span class="rounded-pill bg-idle-bg px-1.5 text-label font-medium tabular-nums text-idle">2</span>'], false);
});
