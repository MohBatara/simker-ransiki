<?php

use App\Enums\Role;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
| Persiapan produksi (Langkah 12A): data awal, akun Admin pertama, cadangan database, keamanan respons.
*/

function asProduction(): void
{
    app()['env'] = 'production';
}

// ---------------------------------------------------------------- Data awal

test('ProductionSeeder hanya mengisi data master tanpa akun dan data contoh', function () {
    $this->seed(ProductionSeeder::class);
    $this->seed(ProductionSeeder::class);

    expect(InstitutionType::count())->toBe(9)
        ->and(BusinessSector::count())->toBe(7)
        ->and(User::count())->toBe(0)
        ->and(Organization::count())->toBe(0);
});

test('DemoSeeder ditolak di produksi tanpa membuat akun uji', function () {
    asProduction();

    expect(fn () => app(DemoSeeder::class)->setContainer(app())->__invoke())
        ->toThrow(RuntimeException::class, 'DemoSeeder hanya untuk environment local');
    expect(User::count())->toBe(0);
});

test('db:seed --class=DemoSeeder di produksi gagal, seeder utama hanya data master', function () {
    asProduction();

    expect(fn () => $this->artisan('db:seed', ['--class' => 'DemoSeeder', '--force' => true])->run())
        ->toThrow(RuntimeException::class);
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0)
        ->and(InstitutionType::count())->toBe(9);
});

// ---------------------------------------------------------------- simker:create-admin

test('simker:create-admin membuat Admin aktif dengan username huruf kecil', function () {
    $this->artisan('simker:create-admin')
        ->expectsQuestion('Nama lengkap', 'Admin Distrik')
        ->expectsQuestion('Username (huruf kecil, angka, titik, minus, garis bawah)', '  Admin.Distrik ')
        ->expectsQuestion('Email (kosongkan bila tidak ada)', 'Admin@Contoh.test')
        ->expectsQuestion('Kata sandi (minimal 8 karakter)', 'rahasia-panjang')
        ->expectsQuestion('Ulangi kata sandi', 'rahasia-panjang')
        ->assertSuccessful();

    $user = User::sole();
    expect($user->username)->toBe('admin.distrik')
        ->and($user->email)->toBe('admin@contoh.test')
        ->and($user->role)->toBe(Role::Admin)
        ->and($user->is_active)->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and(Hash::check('rahasia-panjang', $user->password))->toBeTrue();

    $this->post('/login', ['login' => 'admin.distrik', 'password' => 'rahasia-panjang'])->assertRedirect(route('admin.dashboard'));
});

test('simker:create-admin menanyakan ulang isian yang tidak sah dan email boleh kosong', function () {
    User::factory()->admin()->create(['username' => 'admin']);

    $this->artisan('simker:create-admin')
        ->expectsQuestion('Nama lengkap', 'Admin Kedua')
        ->expectsQuestion('Username (huruf kecil, angka, titik, minus, garis bawah)', 'admin')
        ->expectsOutputToContain('Username sudah dipakai')
        ->expectsQuestion('Username (huruf kecil, angka, titik, minus, garis bawah)', 'admin2')
        ->expectsQuestion('Email (kosongkan bila tidak ada)', '')
        ->expectsQuestion('Kata sandi (minimal 8 karakter)', 'pendek')
        ->expectsQuestion('Ulangi kata sandi', 'pendek')
        ->expectsOutputToContain('Kata sandi paling sedikit 8 karakter')
        ->expectsQuestion('Kata sandi (minimal 8 karakter)', 'rahasia-panjang')
        ->expectsQuestion('Ulangi kata sandi', 'beda-sandinya')
        ->expectsOutputToContain('Konfirmasi kata sandi tidak cocok')
        ->expectsQuestion('Kata sandi (minimal 8 karakter)', 'rahasia-panjang')
        ->expectsQuestion('Ulangi kata sandi', 'rahasia-panjang')
        ->assertSuccessful();

    expect(User::where('username', 'admin2')->sole()->email)->toBeNull();
});

test('simker:create-admin gagal tanpa membuat akun bila opsi tidak sah', function () {
    $this->artisan('simker:create-admin', ['--name' => 'Admin', '--username' => 'Bukan Username!'])
        ->assertFailed();

    expect(User::count())->toBe(0);
});

// ---------------------------------------------------------------- simker:backup-db

test('simker:backup-db menyimpan dump gzip di disk privat tanpa kata sandi di baris perintah', function () {
    Storage::fake('local');
    config(['database.connections.mysql.password' => 'sandi-db-rahasia']);
    Process::fake(['*mysqldump*' => Process::result(output: "-- MySQL dump\nCREATE TABLE `users` (id int);\n")]);

    $this->artisan('simker:backup-db', ['--connection' => 'mysql'])
        ->expectsOutputToContain('Cadangan tersimpan')
        ->assertSuccessful();

    $files = Storage::disk('local')->files('backups');
    expect($files)->toHaveCount(1)
        ->and($files[0])->toMatch('/^backups\/simker-\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz$/')
        ->and(gzdecode(Storage::disk('local')->get($files[0])))->toContain('CREATE TABLE `users`');

    Process::assertRan(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), '--no-tablespaces')
        && str_contains(implode(' ', (array) $process->command), '--defaults-extra-file=')
        && ! str_contains(implode(' ', (array) $process->command), 'sandi-db-rahasia'));
});

test('simker:backup-db hanya menyimpan 14 cadangan terbaru', function () {
    Storage::fake('local');
    Process::fake(['*mysqldump*' => Process::result(output: "CREATE TABLE x;\n")]);

    foreach (range(1, 15) as $day) {
        Storage::disk('local')->put(sprintf('backups/simker-2026-01-%02d-020000.sql.gz', $day), 'lama');
    }
    Storage::disk('local')->put('backups/catatan.txt', 'bukan cadangan');

    $this->artisan('simker:backup-db', ['--connection' => 'mysql'])->assertSuccessful();

    $backups = collect(Storage::disk('local')->files('backups'))->filter(fn ($path) => str_ends_with($path, '.sql.gz'));
    expect($backups)->toHaveCount(14)
        ->and($backups->contains('backups/simker-2026-01-02-020000.sql.gz'))->toBeFalse()
        ->and($backups->contains('backups/simker-2026-01-03-020000.sql.gz'))->toBeTrue()
        ->and(Storage::disk('local')->exists('backups/catatan.txt'))->toBeTrue();
});

test('simker:backup-db gagal tanpa meninggalkan file bila mysqldump gagal', function () {
    Storage::fake('local');
    Process::fake(['*mysqldump*' => Process::result(errorOutput: 'Access denied', exitCode: 2)]);

    $this->artisan('simker:backup-db', ['--connection' => 'mysql'])
        ->expectsOutputToContain('Access denied')
        ->assertFailed();

    expect(Storage::disk('local')->files('backups'))->toBe([]);
});

test('simker:backup-db menolak koneksi selain MySQL', function () {
    config(['database.connections.sqlite_uji' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    $this->artisan('simker:backup-db', ['--connection' => 'sqlite_uji'])->assertFailed();
});

test('simker:backup-db dengan mysqldump sungguhan menghasilkan dump yang lengkap', function () {
    exec('command -v mysqldump', $output, $status);
    if ($status !== 0) {
        $this->markTestSkipped('mysqldump tidak tersedia.');
    }
    Storage::fake('local');

    $this->artisan('simker:backup-db')->assertSuccessful();

    $dump = gzdecode(Storage::disk('local')->get(Storage::disk('local')->files('backups')[0]));
    expect($dump)->toContain('CREATE TABLE `organizations`')
        ->and($dump)->toContain('Dump completed');
});

test('cadangan database dijadwalkan harian pukul 02.00 WIT', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'simker:backup-db'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 2 * * *')
        ->and($event->timezone)->toBe('Asia/Jayapura');
});

// ---------------------------------------------------------------- Keamanan respons

test('semua respons membawa header keamanan dasar', function () {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security');

    $this->get('/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

test('produksi mengalihkan HTTP ke HTTPS dan mengirim HSTS', function () {
    asProduction();

    $this->get('http://localhost/direktori?q=kopi')->assertStatus(301)->assertRedirect('https://localhost/direktori?q=kopi');
    $this->post('http://localhost/login')->assertStatus(308);

    $this->get('https://localhost/')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test('paksa HTTPS bisa dimatikan sementara lewat konfigurasi', function () {
    asProduction();
    config(['simker.security.force_https' => false]);

    $this->get('http://localhost/')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
});

test('cookie session http_only dan route pengembangan tidak dimuat di luar local', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(Route::has('dev.components.app'))->toBeFalse();

    $this->get('/dev/komponen')->assertNotFound();
});

test('setiap halaman memuat favicon yang benar-benar ada', function () {
    $this->get('/')
        ->assertSee('favicon.ico', false)
        ->assertSee('favicon.svg', false)
        ->assertSee('apple-touch-icon.png', false);

    foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png'] as $file) {
        expect(filesize(public_path($file)))->toBeGreaterThan(0);
    }
});

// ---------------------------------------------------------------- Label "Mode uji"

test('label Mode uji tampil di luar produksi dan hilang di produksi', function () {
    $this->get('/login')->assertOk()->assertSee('Mode uji');
    $this->get('/')->assertOk()->assertSee('Mode uji');

    asProduction();

    expect(view('partials.test-mode')->render())->not->toContain('Mode uji');
});
