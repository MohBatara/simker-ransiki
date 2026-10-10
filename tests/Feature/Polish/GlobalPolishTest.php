<?php

use App\Enums\OrganizationStatus;
use App\Enums\ReviewAction;
use App\Models\InstitutionType;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ReportReviewed;

test('bilah atas: lonceng dan menu profil; sidebar tanpa lonceng dan menu Notifikasi', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->kelompok($organization)->create();
    $user->notify(new ReportReviewed(MonthlyReport::factory()->for($organization)->create(), ReviewAction::Verified, null));

    $html = $this->actingAs($user)->get('/kelompok')->assertOk()->getContent();
    $sidebar = substr($html, strpos($html, '<aside'), strpos($html, '</aside>') - strpos($html, '<aside'));

    expect($sidebar)->not->toContain('data-bell')->not->toContain('/notifikasi')->not->toContain('Keluar')
        ->and($html)->toContain('data-bell="desktop"')->toContain('Notifikasi, 1 belum dibaca')
        ->toContain('Menu akun')->toContain('data-logout');

    // Kelompok tidak mendapat kotak cari global.
    expect($html)->not->toContain('aria-label="Cari"');

    $this->get('/notifikasi')->assertSee('aria-current="page"', false)->assertSee('bg-brand-tint text-brand', false);
});

test('blok pengguna: avatar, nama dua baris, dan nama singkat OPD', function () {
    $opd = Opd::factory()->create(['name' => 'Dinas Perikanan Contoh', 'short_name' => 'DPC']);

    $this->actingAs(User::factory()->opd($opd)->create(['name' => 'Petugas Dengan Nama Yang Cukup Panjang']))
        ->get('/opd')
        ->assertSee('<p class="line-clamp-2 text-ui font-semibold text-ink">Petugas Dengan Nama Yang Cukup Panjang</p>', false)
        ->assertSee('OPD · DPC')
        ->assertSee('>PD</span>', false);
});

test('daftar kelompok: kotak cari berikon, filter ringkas, chip yang bisa dihapus, ringkasan hasil', function () {
    $admin = User::factory()->admin()->create();
    $type = InstitutionType::factory()->create(['name' => 'KUB']);
    Organization::factory()->count(2)->create(['institution_type_id' => $type->id]);
    Organization::factory()->create(['institution_type_id' => $type->id, 'status' => OrganizationStatus::Inactive, 'name' => 'KUB Diam']);

    $this->actingAs($admin)->get('/admin/kelompok?type='.$type->id.'&q=KUB')
        ->assertOk()
        ->assertSee('type="search"', false)
        ->assertSee('enterkeyhint="search"', false)
        ->assertSee('Filter (1)')
        ->assertSee('md:h-9', false)
        ->assertSee('aria-label="Hapus filter Jenis: KUB"', false)
        ->assertSee('Hapus semua')
        ->assertSee('1 kelompok · 1 tidak aktif');
});

test('daftar kelompok: avatar + nama + jenis · kampung; badge status hanya bila bukan Aktif', function () {
    $active = Organization::factory()->create(['name' => 'KUB Aktif Contoh']);
    $closed = Organization::factory()->create(['name' => 'KUB Tutup Contoh', 'status' => OrganizationStatus::Closed]);

    $html = $this->actingAs(User::factory()->admin()->create())->get('/admin/kelompok')->assertOk()
        ->assertDontSee('Jenis · kampung')
        ->assertSee($active->institutionType->name.' · '.$active->village->name)
        ->getContent();

    expect(substr_count($html, '>Aktif</span>'))->toBe(0)
        ->and($html)->toContain('>Ditutup</span>');
});

test('scroll-padding untuk header dan bar aksi yang menempel', function () {
    $organization = Organization::factory()->create();
    $this->actingAs(User::factory()->kelompok($organization)->create());

    $this->get('/kelompok')->assertSee('<html lang="id" class="scroll-pt-20 md:scroll-pt-6 max-md:scroll-pb-20">', false);
    $this->get('/kelompok/profil/ubah')->assertSee('max-md:scroll-pb-36', false);
});

test('daftar pengguna dan laporan memakai ringkasan hasil', function () {
    $this->actingAs(User::factory()->admin()->create());
    User::factory()->admin()->create(['is_active' => false]);

    $this->get('/admin/pengguna')->assertSee('2 akun · 1 nonaktif');
    $this->get('/admin/laporan')->assertSee('0 laporan');
});
