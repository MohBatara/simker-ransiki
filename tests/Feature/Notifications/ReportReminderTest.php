<?php

use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ReportReminder;
use App\Support\NotificationText;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 08:00:00', 'Asia/Jayapura'));

    $this->make = function (string $name, array $attributes = []) {
        $organization = Organization::factory()->create(['name' => $name, 'reporting_starts_on' => '2026-07-01', ...$attributes]);

        return [$organization, User::factory()->kelompok($organization)->create()];
    };
});

afterEach(fn () => Carbon::setTestNow());

test('pengingat hanya untuk kelompok wajib lapor yang belum mengajukan, ke akun aktif', function () {
    [$missing, $missingUser] = ($this->make)('Belum dibuat');
    $secondUser = User::factory()->kelompok($missing)->create();
    $inactiveUser = User::factory()->kelompok($missing)->create(['is_active' => false]);
    [$draft, $draftUser] = ($this->make)('Masih draf');
    MonthlyReport::factory()->for($draft)->create(['period' => '2026-09-01']);
    [$idle, $idleUser] = ($this->make)('Tidak aktif', ['status' => OrganizationStatus::Inactive]);

    [$submitted, $submittedUser] = ($this->make)('Sudah diajukan');
    MonthlyReport::factory()->for($submitted)->status(ReportStatus::Submitted)->create(['period' => '2026-09-01', 'submitted_at' => now()]);
    [$revision, $revisionUser] = ($this->make)('Sedang revisi');
    MonthlyReport::factory()->for($revision)->status(ReportStatus::Revision)->create(['period' => '2026-09-01', 'submitted_at' => now()]);
    [, $closedUser] = ($this->make)('Tutup', ['status' => OrganizationStatus::Closed]);
    [, $newUser] = ($this->make)('Baru mulai', ['reporting_starts_on' => '2026-10-01']);
    $opdUser = User::factory()->opd(Opd::factory()->create())->create();

    $this->artisan('simker:pengingat-laporan')
        ->expectsOutput('Pengingat laporan September 2026 dikirim ke 4 akun dari 3 kelompok.')
        ->assertSuccessful();

    foreach ([$missingUser, $secondUser, $draftUser, $idleUser] as $user) {
        expect($user->notifications()->where('type', ReportReminder::class)->count())->toBe(1);
    }
    foreach ([$inactiveUser, $submittedUser, $revisionUser, $closedUser, $newUser, $opdUser] as $user) {
        expect($user->notifications()->count())->toBe(0);
    }
});

test('dijalankan dua kali tidak membuat pengingat ganda; tahap akhir tetap terkirim', function () {
    [, $user] = ($this->make)('Belum dibuat');

    $this->artisan('simker:pengingat-laporan')->assertSuccessful();
    $this->artisan('simker:pengingat-laporan')->expectsOutput('Tidak ada kelompok yang perlu diingatkan.')->assertSuccessful();
    expect($user->notifications()->count())->toBe(1);

    // H-3 tenggat (10 Oktober) = 7 Oktober.
    Carbon::setTestNow(Carbon::parse('2026-10-07 08:00:00', 'Asia/Jayapura'));
    $this->artisan('simker:pengingat-laporan')->assertSuccessful();
    $this->artisan('simker:pengingat-laporan --tahap=akhir')->assertSuccessful();

    expect($user->notifications()->count())->toBe(2)
        ->and($user->notifications()->pluck('data')->pluck('stage')->sort()->values()->all())->toBe(['akhir', 'awal']);
});

test('teks pengingat tahap awal dan akhir', function () {
    [, $user] = ($this->make)('Belum dibuat');

    $this->artisan('simker:pengingat-laporan --tahap=awal');
    expect(NotificationText::describe($user->notifications()->sole())['text'])
        ->toBe('Laporan September 2026 sudah bisa diajukan. Tenggat 10 Oktober 2026.');

    Carbon::setTestNow(Carbon::parse('2026-10-08 08:00:00', 'Asia/Jayapura'));
    $this->artisan('simker:pengingat-laporan --tahap=akhir');
    $last = $user->notifications()->where('data->stage', 'akhir')->sole();

    // Dibaca dua hari kemudian, teksnya tetap mengikuti tanggal kirim.
    Carbon::setTestNow(Carbon::parse('2026-10-10 08:00:00', 'Asia/Jayapura'));
    expect(NotificationText::describe($last)['text'])->toBe('Tenggat laporan September 2026 tinggal 2 hari, yaitu 10 Oktober 2026.');
});

test('membuka pengingat mengarah ke daftar laporan kelompok', function () {
    [, $user] = ($this->make)('Belum dibuat');
    $this->artisan('simker:pengingat-laporan');

    $this->actingAs($user)->get('/notifikasi/'.$user->notifications()->sole()->id)->assertRedirect('/kelompok/laporan');
});

test('hari tanpa jadwal tidak mengirim apa pun', function () {
    [, $user] = ($this->make)('Belum dibuat');

    Carbon::setTestNow(Carbon::parse('2026-10-08 08:00:00', 'Asia/Jayapura'));
    $this->artisan('simker:pengingat-laporan')->expectsOutput('Hari ini tidak ada jadwal notifikasi tenggat.')->assertSuccessful();

    expect($user->notifications()->count())->toBe(0);
});

test('pilihan tidak sah ditolak', function () {
    $this->artisan('simker:pengingat-laporan --tahap=tengah')->assertFailed();
    $this->artisan('simker:pengingat-laporan --periode=bulan-lalu')->assertFailed();
});

test('dijadwalkan setiap hari pukul 08.00 WIT', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command, 'simker:pengingat-laporan'))
        ->map(fn ($event) => [$event->expression, $event->timezone])
        ->values()->all();

    expect($events)->toBe([['0 8 * * *', 'Asia/Jayapura']]);
});

test('tombol salin pesan pengingat di dasbor OPD dan Admin', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 09:00:00', 'Asia/Jayapura'));
    $opd = Opd::factory()->create(['name' => 'Dinas Pertanian Contoh']);
    [$organization] = ($this->make)('KUB Belum Lapor', ['leader_name' => 'Ketua Contoh', 'phone' => '0812-0000-0000']);
    $organization->opds()->attach($opd);

    $this->actingAs(User::factory()->opd($opd)->create())->get('/opd')
        ->assertOk()
        ->assertSee('Salin pesan pengingat')
        ->assertSee('Ketua: Ketua Contoh')
        ->assertSee('tel:081200000000', false)
        ->assertSee('Yth. pengurus KUB Belum Lapor')
        ->assertSee('Laporan bulanan September 2026 belum kami terima.')
        ->assertSee('Tenggat pengiriman sudah lewat (10 Oktober 2026). Mohon segera diajukan.')
        ->assertSee(str_replace('/', '\/', route('login')), false)
        ->assertSee('Dinas Pertanian Contoh');

    $this->actingAs(User::factory()->admin()->create())->get('/admin')
        ->assertOk()
        ->assertSee('Salin pesan pengingat')
        ->assertSee('Admin Distrik Ransiki');
});
