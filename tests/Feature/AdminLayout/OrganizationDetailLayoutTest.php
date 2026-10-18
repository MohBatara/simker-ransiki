<?php

use App\Enums\ReportStatus;
use App\Models\Attachment;
use App\Models\BusinessSector;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\Village;
use App\Support\OrganizationOverview;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->village = Village::factory()->create(['name' => 'Kampung Contoh 3']);
    $this->organization = Organization::factory()->create(['village_id' => $this->village->id, 'address' => 'Jl. Contoh No. 5', 'description' => 'Deskripsi contoh kelompok.']);
    $this->organization->sectors()->attach(BusinessSector::factory()->create(['name' => 'Perkebunan']));
    $this->admin = User::factory()->admin()->create();
});

test('kepala: jenis, kampung, badge status dan verifikasi, bidang usaha, remah, strip angka, dan tab berikon', function () {
    $this->actingAs($this->admin)->get("/admin/kelompok/{$this->organization->id}")->assertOk()
        ->assertSee('Kampung Contoh 3, Distrik Ransiki')
        ->assertSeeInOrder([$this->organization->institutionType->name, 'aria-label="Status kelompok"', 'Belum diverifikasi', 'Perkebunan'], false)
        ->assertSeeInOrder(['Anggota', 'Produk', 'Izin terbit', 'Laporan terverifikasi'])
        ->assertSeeInOrder(['Kelompok', $this->organization->name], false)
        ->assertSeeInOrder(['Akun', '<span class="rounded-full px-2 text-caption tabular-nums bg-subtle text-ink-soft">0</span>'], false);
});

test('tautan Google Maps dibentuk dari alamat dan ter-encode', function () {
    expect(OrganizationOverview::mapsUrl($this->organization->load('village')))
        ->toBe('https://www.google.com/maps/search/?api=1&query='.rawurlencode('Jl. Contoh No. 5, Kampung Contoh 3, Distrik Ransiki, Manokwari Selatan'));

    $this->actingAs($this->admin)->get("/admin/kelompok/{$this->organization->id}")
        ->assertSee('Buka di Google Maps')
        ->assertSee('target="_blank" rel="noopener"', false);
});

test('kartu foto: foto produk lalu lampiran gambar, PDF tidak ikut', function () {
    Product::factory()->for($this->organization)->create(['photo_path' => 'products/a.jpg']);
    $image = Attachment::factory()->create(['attachable_id' => $this->organization->id, 'mime_type' => 'image/jpeg', 'original_name' => 'kegiatan.jpg']);
    $pdf = Attachment::factory()->create(['attachable_id' => $this->organization->id, 'mime_type' => 'application/pdf']);

    $photos = OrganizationOverview::data($this->organization)['photos'];

    expect($photos)->toHaveCount(2)
        ->and($photos[0]['url'])->toContain('/foto-produk/')
        ->and($photos[1]['url'])->toContain('/lampiran/'.$image->id)
        ->and($photos->pluck('url')->join(' '))->not->toContain('/lampiran/'.$pdf->id);
});

test('ikon judul kartu hanya di detail kelompok', function () {
    $this->actingAs($this->admin);

    $this->get("/admin/kelompok/{$this->organization->id}")->assertSee('<h2 class="flex items-center gap-2 text-body font-semibold text-ink">', false)
        ->assertSee('size-5 shrink-0 text-brand', false);
    $this->get('/admin')->assertDontSee('size-5 shrink-0 text-brand', false);
    $this->get('/admin/laporan')->assertDontSee('size-5 shrink-0 text-brand', false);
});

test('menu "…" Admin: hapus untuk kelompok tanpa laporan/akun, tandai ditutup bila ada laporan', function () {
    $this->actingAs($this->admin)->get("/admin/kelompok/{$this->organization->id}")
        ->assertSee('aria-label="Tindakan lain"', false)
        ->assertSee('Hapus kelompok')
        ->assertSee('name="_method" value="DELETE"', false);

    MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Submitted)->create(['submitted_at' => now()]);
    $this->get("/admin/kelompok/{$this->organization->id}")->assertSee('Ubah status')->assertSee('tidak bisa dihapus. Bila sudah berhenti, tandai ditutup.')->assertDontSee('Hapus kelompok');

    $opd = Opd::factory()->create();
    $this->organization->opds()->attach($opd);
    $this->actingAs(User::factory()->opd($opd)->create())->get("/opd/kelompok/{$this->organization->id}")
        ->assertOk()->assertDontSee('aria-label="Tindakan lain"', false);
});

test('jumlah query detail kelompok terbatas', function () {
    Product::factory()->for($this->organization)->count(6)->create();
    MonthlyReport::factory()->for($this->organization)->create();
    $this->actingAs($this->admin);

    DB::enableQueryLog();
    $this->get("/admin/kelompok/{$this->organization->id}")->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(30);
});
