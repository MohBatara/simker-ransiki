<?php

use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Models\BusinessSector;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\Village;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->opd = Opd::factory()->create();
    $this->villageA = Village::factory()->create();
    $this->villageB = Village::factory()->create();
    $this->sectorA = BusinessSector::factory()->create();
    $this->sectorB = BusinessSector::factory()->create();

    $this->alpha = Organization::factory()->create(['name' => 'Alfa Contoh', 'village_id' => $this->villageA->id, 'members_count' => 20]);
    $this->alpha->sectors()->attach([$this->sectorA->id, $this->sectorB->id]);
    $this->alpha->opds()->attach($this->opd);
    $this->beta = Organization::factory()->create(['name' => 'Beta Contoh', 'village_id' => $this->villageA->id, 'members_count' => null]);
    $this->beta->sectors()->attach($this->sectorA);
    $this->beta->opds()->attach($this->opd);
    $this->gamma = Organization::factory()->create(['name' => 'Gamma Lain', 'village_id' => $this->villageB->id, 'members_count' => 5, 'status' => OrganizationStatus::Inactive]);

    $this->admin = User::factory()->admin()->create();
});

test('kartu ringkasan dari data nyata: unik, anggota NULL tidak jadi nol', function () {
    $response = $this->actingAs($this->admin)->get('/admin/kelompok')->assertOk();

    expect($response->viewData('totals'))->toBe([
        'organizations' => 3, 'villages' => 2, 'sectors' => 2, 'members' => 25, 'members_filled' => 2,
    ]);
    $response->assertSee('Total kelompok')->assertSee('diisi 2 dari 3 kelompok')->assertDontSee('sesuai filter');
});

test('kartu ringkasan mengikuti filter dan diberi keterangan "sesuai filter"', function () {
    $response = $this->actingAs($this->admin)->get('/admin/kelompok?village='.$this->villageA->id)->assertOk();

    expect($response->viewData('totals'))->toBe([
        'organizations' => 2, 'villages' => 1, 'sectors' => 2, 'members' => 20, 'members_filled' => 1,
    ]);
    $response->assertSee('sesuai filter');
});

test('OPD hanya menghitung kelompok binaan', function () {
    $response = $this->actingAs(User::factory()->opd($this->opd)->create())->get('/opd/kelompok')->assertOk();

    expect($response->viewData('totals')['organizations'])->toBe(2);
});

test('panel detail dirender bersama halaman untuk baris pertama, atau ?pilih=id', function () {
    Product::factory()->for($this->beta)->create(['name' => 'Produk Panel Beta']);
    MonthlyReport::factory()->for($this->beta)->status(ReportStatus::Verified)->create(['period' => '2026-09-01', 'submitted_at' => now()]);
    $this->actingAs($this->admin);

    $first = $this->get('/admin/kelompok')->assertOk();
    expect($first->viewData('panel')['organization']->id)->toBe($this->alpha->id);
    $first->assertSee('data-panel-id="'.$this->alpha->id.'"', false)
        ->assertSee('data-id="'.$this->alpha->id.'" data-selected="true"', false)
        ->assertSee('Lihat detail');

    $this->get('/admin/kelompok?pilih='.$this->beta->id)
        ->assertSee('data-panel-id="'.$this->beta->id.'"', false)
        ->assertSee('Produk Panel Beta')
        ->assertSee('September 2026');
});

test('?pilih untuk kelompok yang tidak terlihat diabaikan', function () {
    $this->actingAs(User::factory()->opd($this->opd)->create())
        ->get('/opd/kelompok?pilih='.$this->gamma->id)
        ->assertOk()
        ->assertDontSee('Gamma Lain');
});

test('potongan panel ringkas dicek Policy', function () {
    $this->actingAs($this->admin)->get("/admin/kelompok/{$this->gamma->id}/ringkas")
        ->assertOk()->assertSee('Gamma Lain')->assertSee('Tidak aktif')->assertDontSee('<html', false);

    $this->actingAs(User::factory()->opd($this->opd)->create())->get("/opd/kelompok/{$this->gamma->id}/ringkas")->assertNotFound();
    $this->actingAs(User::factory()->opd(Opd::factory()->create())->create())->get("/opd/kelompok/{$this->alpha->id}/ringkas")->assertNotFound();
    $this->actingAs(User::factory()->kelompok($this->alpha)->create())->get("/admin/kelompok/{$this->alpha->id}/ringkas")->assertForbidden();
});

test('jumlah query daftar terbatas (tanpa N+1)', function () {
    Organization::factory()->count(15)->create()->each(fn ($organization) => $organization->sectors()->attach($this->sectorA));
    $this->actingAs($this->admin);

    DB::enableQueryLog();
    $this->get('/admin/kelompok')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(30);
});

test('bilah alat: cari + 3 filter sebaris, sisanya di panel "Filter lain", langsung diterapkan tanpa Terapkan/Reset', function () {
    $html = $this->actingAs($this->admin)->get('/admin/kelompok?sector='.$this->sectorA->id.'&village='.$this->villageA->id)->assertOk()
        ->assertSee('x-data="adminToolbar"', false)
        ->assertSee('Filter (2)')
        ->assertSee('Filter lain (1)')
        ->assertDontSee('Terapkan</span>', false)
        ->assertDontSee('>Reset<', false)
        ->getContent();

    $panel = strpos($html, 'id="panel-filter"');

    // Sebaris (sebelum panel): kampung, jenis, status. Di panel: bidang usaha dan OPD (plus salinan filter utama untuk HP).
    expect(strpos($html, 'id="village-inline"'))->toBeLessThan($panel)
        ->and(strpos($html, 'id="type-inline"'))->toBeLessThan($panel)
        ->and(strpos($html, 'id="status-inline"'))->toBeLessThan($panel)
        ->and($html)->not->toContain('id="sector-inline"')
        ->and(strpos($html, 'id="sector-panel"'))->toBeGreaterThan($panel)
        ->and(strpos($html, 'id="opd-panel"'))->toBeGreaterThan($panel)
        // Hasil yang ditukar saat filter berubah.
        ->and($html)->toContain('data-swap="kelompok"')->toContain('data-swap="toolbar-summary"');
});

test('dengan panel detail (≥ 1280px): kolom bidang usaha disembunyikan, nama satu baris dengan title', function () {
    $this->actingAs($this->admin)->get('/admin/kelompok')->assertOk()
        ->assertSee('px-4 text-left md:max-xl:hidden xl:hidden">Bidang usaha</th>', false)
        ->assertSee('sticky top-16 z-10', false)
        ->assertSee('class="block xl:truncate" title="Alfa Contoh"', false)
        ->assertSee('xl:whitespace-nowrap', false);
});
