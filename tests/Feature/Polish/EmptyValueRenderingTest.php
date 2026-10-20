<?php

use App\Enums\IssueKind;
use App\Enums\LicenseStatus;
use App\Enums\ReportStatus;
use App\Models\License;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/*
 * Nilai kosong ("Belum diisi") adalah HTML aman (HtmlString). Bila tidak sengaja diubah ke string (mis. orEmpty dipanggil
 * dua kali, atau disambung dengan teks), Blade meng-escape-nya dan kode <span ...> tampil mentah. Halaman utama tiap peran
 * dibuka dengan data yang kolomnya kosong; respons tidak boleh mengandung kode HTML yang ter-escape.
 */
beforeEach(function () {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'Asia/Jayapura'));

    $this->opd = Opd::factory()->create();
    // Kolom profil kosong: alamat, telepon, email, ketua, anggota, tahun berdiri, deskripsi, dan seterusnya.
    $this->organization = Organization::factory()->public()->create(['reporting_starts_on' => '2026-07-01']);
    $this->organization->opds()->attach($this->opd);
    $this->product = Product::factory()->for($this->organization)->create(['unit' => null, 'capacity' => null, 'is_public' => true]);
    $this->license = License::factory()->for($this->organization)->create(['number' => null, 'notes' => null, 'status' => LicenseStatus::InProcess]);

    // Laporan diajukan tanpa angka keuangan dan dengan baris produksi kosong.
    $this->report = MonthlyReport::factory()->for($this->organization)->status(ReportStatus::Submitted)->create([
        'period' => '2026-09-01', 'submitted_at' => now()->subDays(12),
    ]);
    $this->report->items()->create(['product_name' => null, 'production_qty' => null, 'sales_qty' => null, 'revenue' => null]);
    $this->issue = $this->report->issues()->create(['kind' => IssueKind::Support, 'category' => 'pelatihan', 'description' => null]);

    // Akun tanpa email dan telepon.
    $this->admin = User::factory()->admin()->create(['email' => null, 'phone' => null]);
    $this->opdUser = User::factory()->opd($this->opd)->create(['email' => null, 'phone' => null]);
    $this->member = User::factory()->kelompok($this->organization)->create(['email' => null, 'phone' => null]);

    // Riwayat dengan nilai lama kosong.
    $this->actingAs($this->admin);
    $this->organization->update(['address' => 'Jalan Contoh 1']);

    $this->post = Post::factory()->create(['cover_photo_path' => null]);
});

function assertNoEscapedHtml(mixed $test, string $url): void
{
    $content = $test->get($url)->assertOk()->getContent();

    foreach (['&lt;span', '&lt;div', '&lt;/span', '&lt;p'] as $escaped) {
        expect(str_contains($content, $escaped))->toBeFalse("{$url} memuat kode HTML ter-escape ({$escaped}).");
    }
}

test('Admin: halaman utama tanpa kode HTML ter-escape, nilai kosong tampil "Belum diisi"', function () {
    $id = $this->organization->id;
    $this->actingAs($this->admin);

    foreach ([
        '/admin', '/admin/dasbor/ekonomi', '/admin/dasbor/legalitas',
        '/admin/kelompok', "/admin/kelompok?pratinjau={$id}", "/admin/kelompok/{$id}/ringkas", "/admin/kelompok/{$id}",
        "/admin/kelompok/{$id}/produk", "/admin/kelompok/{$id}/izin", "/admin/kelompok/{$id}/izin/{$this->license->id}",
        "/admin/kelompok/{$id}/laporan", "/admin/kelompok/{$id}/akun", "/admin/kelompok/{$id}/riwayat", "/admin/kelompok/{$id}/ubah",
        '/admin/kelompok/verifikasi-profil', '/admin/kelompok/data-ganda',
        '/admin/laporan', '/admin/laporan/verifikasi', "/admin/laporan/{$this->report->id}",
        '/admin/laporan/masalah', "/admin/laporan/masalah/{$this->issue->id}",
        '/admin/pengaturan/pengguna', "/admin/pengaturan/pengguna/{$this->admin->id}", "/admin/pengaturan/pengguna/{$this->member->id}",
        '/admin/website', '/admin/website/profil', '/admin/website/halaman', '/admin/website/berita',
        '/akun/kata-sandi', '/notifikasi',
    ] as $url) {
        assertNoEscapedHtml($this, $url);
    }

    $this->get("/admin/pengaturan/pengguna/{$this->member->id}")->assertSee(Format::EMPTY_LABEL);
});

test('OPD: halaman utama tanpa kode HTML ter-escape', function () {
    $id = $this->organization->id;
    $this->actingAs($this->opdUser);

    foreach ([
        '/opd', '/opd/dasbor/ekonomi', '/opd/dasbor/legalitas',
        '/opd/kelompok', "/opd/kelompok/{$id}/ringkas", "/opd/kelompok/{$id}", "/opd/kelompok/{$id}/produk",
        "/opd/kelompok/{$id}/izin", "/opd/kelompok/{$id}/izin/{$this->license->id}", "/opd/kelompok/{$id}/laporan",
        "/opd/kelompok/{$id}/riwayat", '/opd/kelompok/verifikasi-profil',
        '/opd/laporan', '/opd/laporan/verifikasi', "/opd/laporan/{$this->report->id}",
        '/opd/laporan/masalah', "/opd/laporan/masalah/{$this->issue->id}",
        '/akun/kata-sandi', '/notifikasi',
    ] as $url) {
        assertNoEscapedHtml($this, $url);
    }
});

test('Kelompok: halaman utama tanpa kode HTML ter-escape, Akun saya menampilkan "Belum diisi" untuk telepon', function () {
    $this->actingAs($this->member);

    foreach ([
        '/kelompok', '/kelompok/profil', '/kelompok/profil/ubah', '/kelompok/produk', '/kelompok/izin',
        "/kelompok/izin/{$this->license->id}", '/kelompok/laporan', "/kelompok/laporan/{$this->report->id}",
        '/akun/kata-sandi', '/notifikasi',
    ] as $url) {
        assertNoEscapedHtml($this, $url);
    }

    $this->get('/akun/kata-sandi')->assertSee('Telepon')->assertSee(Format::EMPTY_LABEL);
});

test('publik: halaman utama tanpa kode HTML ter-escape', function () {
    auth()->logout();

    foreach ([
        '/', '/profil', '/kontak', '/direktori', "/direktori/{$this->organization->slug}",
        '/produk', "/produk/{$this->product->slug}", '/berita', "/berita/{$this->post->slug}",
    ] as $url) {
        assertNoEscapedHtml($this, $url);
    }
});

test('nilai kosong: HTML aman di tampilan (juga bila dipanggil dua kali), teks polos untuk ekspor dan kalimat', function () {
    $once = Format::orEmpty(null);
    $twice = Format::orEmpty($once);

    expect($twice)->toBe($once)
        ->and(e($twice))->toContain('<span')->not->toContain('&lt;')
        ->and(Format::orEmpty('<b>data pengguna</b>'))->toBe('<b>data pengguna</b>')
        ->and(e(Format::orEmpty('<b>data pengguna</b>')))->toBe('&lt;b&gt;data pengguna&lt;/b&gt;')
        ->and(Format::emptyText(null))->toBe('Belum diisi')
        ->and(Format::emptyText(''))->toBe('Belum diisi')
        ->and(Format::emptyText(Format::EMPTY))->toBe('Belum diisi')
        ->and(Format::emptyText($once))->toBe('Belum diisi')
        ->and(Format::emptyText('0812'))->toBe('0812');
});
