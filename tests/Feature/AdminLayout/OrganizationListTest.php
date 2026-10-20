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

test('klik baris membuka popup pratinjau (tanpa pindah halaman); ?pratinjau=id membuka popup sejak awal', function () {
    Product::factory()->for($this->beta)->create(['name' => 'Produk Panel Beta']);
    MonthlyReport::factory()->for($this->beta)->status(ReportStatus::Verified)->create(['period' => '2026-09-01', 'submitted_at' => now()]);
    $this->actingAs($this->admin);

    // Tanpa ?pratinjau: tidak ada popup terbuka; baris membawa id pratinjau, nama tetap tautan ke detail (Ctrl/Cmd + klik).
    $first = $this->get('/admin/kelompok')->assertOk()
        ->assertSee('x-data="organizationPanel(', false)
        ->assertSee('x-on:click="select($event)"', false)
        ->assertSee('data-preview-id="'.$this->alpha->id.'"', false)
        ->assertSee('href="'.route('admin.organizations.show', $this->alpha).'" data-row-link', false)
        ->assertDontSee('data-modal="organization-preview" data-open-on-load', false)
        ->assertSee('Buka detail');
    expect($first->viewData('panel'))->toBeNull();

    $this->get('/admin/kelompok?pratinjau='.$this->beta->id)
        ->assertSee('data-modal="organization-preview" data-open-on-load', false)
        ->assertSee('data-panel-id="'.$this->beta->id.'"', false)
        ->assertSee('data-preview-id="'.$this->beta->id.'" data-selected="true"', false)
        ->assertSee('Produk Panel Beta')
        ->assertSee('September 2026')
        ->assertSee('Kelompok berikutnya')
        ->assertSee('x-on:keydown.arrow-right="go(1)"', false);

    // Alamat lama ?pilih tetap membuka popup.
    $this->get('/admin/kelompok?pilih='.$this->beta->id)->assertSee('data-modal="organization-preview" data-open-on-load', false);

    // Isi popup juga bisa dimuat lewat route ringkas (dipakai saat baris diklik).
    $this->get("/admin/kelompok/{$this->beta->id}/ringkas")->assertOk()->assertSee('Produk Panel Beta');
});

test('OPD hanya bisa membuka pratinjau kelompok binaannya', function () {
    $opdUser = User::factory()->opd($this->opd)->create();
    $this->actingAs($opdUser)->get("/opd/kelompok/{$this->alpha->id}/ringkas")->assertOk();
    $this->get("/opd/kelompok/{$this->gamma->id}/ringkas")->assertNotFound();
    $this->get('/opd/kelompok?pratinjau='.$this->gamma->id)->assertOk()
        ->assertDontSee('data-modal="organization-preview" data-open-on-load', false)
        ->assertDontSee('Gamma Lain');
});

test('tab Verifikasi profil dan Kemungkinan data ganda juga membuka popup pratinjau', function () {
    $this->alpha->update(['profile_status' => App\Enums\ProfileStatus::Unverified]);
    $this->actingAs($this->admin);

    $this->get('/admin/kelompok/verifikasi-profil?jenis=baru')->assertOk()
        ->assertSee('x-data="organizationPanel(', false)
        ->assertSee('data-preview-id="'.$this->alpha->id.'"', false)
        ->assertSee('data-modal="organization-preview"', false);

    $this->get('/admin/kelompok/data-ganda')->assertOk()
        ->assertSee('x-data="organizationPanel(', false)
        ->assertSee('data-modal="organization-preview"', false);
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

test('kepala kartu daftar: judul, cari kecil, dan tombol Filter membuka popup berisi semua filter dengan Terapkan dan Atur ulang', function () {
    $html = $this->actingAs($this->admin)->get('/admin/kelompok?sector='.$this->sectorA->id.'&village='.$this->villageA->id)->assertOk()
        ->assertSee('x-data="adminToolbar"', false)
        ->assertSee('Daftar kelompok')
        ->assertSee('Filter (2)')
        ->assertSee('aria-haspopup="dialog" aria-controls="panel-filter"', false)
        ->assertSee('Terapkan</span>', false)
        ->assertSee('Atur ulang</span>', false)
        ->getContent();

    $panel = strpos($html, 'id="panel-filter"');

    // Semua filter ada di popup Filter (x-admin.modal md), tanpa select sebaris; tidak ada lagi laci samping.
    expect($html)->not->toContain('-inline"')
        ->and(strpos($html, 'id="village-panel"'))->toBeGreaterThan($panel)
        ->and(strpos($html, 'id="sector-panel"'))->toBeGreaterThan($panel)
        ->and(strpos($html, 'id="opd-panel"'))->toBeGreaterThan($panel)
        // Hasil yang ditukar saat filter berubah.
        ->and($html)->toContain('data-swap="kelompok"')->toContain('data-swap="toolbar-summary"')
        ->and($html)->toMatch('#<dialog[^>]*id="panel-filter"#')->not->toContain('translate-x-full');
});

test('pratinjau kelompok berupa popup lg di tengah, tabel tetap lebar: jenis sebagai chip, kepala tabel menempel', function () {
    $this->actingAs($this->admin)->get('/admin/kelompok')->assertOk()
        ->assertSee('>Bidang usaha</th>', false)
        ->assertSee('md:sticky md:top-0 md:z-10', false)
        ->assertSee('data-modal="organization-preview"', false)
        ->assertSee('md:max-w-[760px]', false)
        ->assertDontSee('fixed inset-y-0 right-0 z-50', false)
        ->assertSee('rounded-pill border border-line bg-panel', false);
});
