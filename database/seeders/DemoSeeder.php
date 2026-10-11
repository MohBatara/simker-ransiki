<?php

namespace Database\Seeders;

use App\Enums\IssueKind;
use App\Enums\LegalStatus;
use App\Enums\LicenseStatus;
use App\Enums\OrganizationStatus;
use App\Enums\ProblemCategory;
use App\Enums\ProfileStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Enums\Role;
use App\Enums\SupportCategory;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Post;
use App\Models\ReportReview;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Village;
use App\Support\Format;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Data contoh untuk pengembangan (CLAUDE.md Bagian 10). Semua nama dan nomor jelas bukan data resmi.
 * Tidak memakai angka acak: setiap variasi diturunkan dari nomor kelompok, sehingga
 * migrate:fresh --seed selalu menghasilkan data yang sama.
 */
class DemoSeeder extends Seeder
{
    private const ORGANIZATION_COUNT = 25;

    private const PERIODS = ['2026-07-01', '2026-08-01', '2026-09-01'];

    /** Riwayat terverifikasi sebelum PERIODS, agar grafik Statistik 12 bulan terlihat berisi. */
    private const HISTORY_PERIODS = ['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01', '2026-06-01'];

    /** short_name => nama OPD (sesuai blueprint klien; akan diganti nama resmi). */
    private const OPDS = [
        'Pertanian' => 'Dinas Pertanian dan Perkebunan',
        'DPMPTSP' => 'DPMPTSP',
        'Dinkopdag' => 'Dinkopdag',
        'Perikanan' => 'Dinas Perikanan',
    ];

    /** Kelompok yang hanya dibina Dinas Perikanan, sehingga OPD itu tidak berbagi kelompok. */
    private const FISHERY_ONLY = [5, 10, 15, 20, 25];

    private const INACTIVE = [8, 14, 21];

    private const CLOSED = [23];

    private const NOT_PUBLIC = [4, 18];

    /** Kelompok baru: wajib lapor mulai September 2026. */
    private const REPORTING_FROM_SEPTEMBER = [22, 25];

    /** Jenis kelembagaan kelompok ke-1 s.d. ke-25 (kode MasterDataSeeder). */
    private const TYPES = [
        'kub', 'kelompok_pertanian', 'bumdes', 'umkm', 'kelompok_perikanan',
        'kelompok_perkebunan', 'kdmp', 'kub', 'umkm', 'kelompok_perikanan',
        'kelompok_pertanian', 'knmp', 'umkm', 'kelompok_perkebunan', 'kelompok_perikanan',
        'bumdes', 'kub', 'kelompok_pertanian', 'umkm', 'kelompok_perikanan',
        'lainnya', 'kelompok_perkebunan', 'umkm', 'kub', 'kelompok_perikanan',
    ];

    private const PRIMARY_SECTOR = [
        'bumdes' => 'Perdagangan',
        'kub' => 'Pengolahan Hasil',
        'kdmp' => 'Perdagangan',
        'knmp' => 'Perikanan',
        'kelompok_pertanian' => 'Pertanian',
        'kelompok_perkebunan' => 'Perkebunan',
        'kelompok_perikanan' => 'Perikanan',
        'umkm' => 'UMKM',
        'lainnya' => 'Lainnya',
    ];

    private const EXTRA_SECTORS = ['Pengolahan Hasil', 'Perdagangan', 'UMKM', 'Pertanian', 'Perkebunan'];

    /** Bidang usaha utama => [nama produk, satuan]. */
    private const PRODUCTS = [
        'Pertanian' => [['Cabai rawit', 'kg'], ['Sayur kangkung', 'ikat']],
        'Perkebunan' => [['Kakao kering', 'kg'], ['Kopi bubuk', 'kg'], ['Pinang', 'kg']],
        'Perikanan' => [['Ikan asap', 'ekor'], ['Abon ikan', 'bungkus']],
        'UMKM' => [['Keripik pisang', 'bungkus'], ['Keripik keladi', 'bungkus']],
        'Perdagangan' => [['Sagu', 'kg'], ['Minyak kelapa', 'liter']],
        'Pengolahan Hasil' => [['Sagu lempeng', 'bungkus'], ['Gula aren', 'kg']],
        'Lainnya' => [['Anyaman', 'buah']],
    ];

    public function run(): void
    {
        // Akun uji berkata sandi "password": menolak dengan error (bukan diam-diam dilewati) agar
        // db:seed --class=DemoSeeder di server gagal dengan jelas.
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoSeeder hanya untuk environment local. Di server gunakan db:seed --class=ProductionSeeder.');
        }

        $this->call(MasterDataSeeder::class);

        $opds = collect(self::OPDS)->map(
            fn (string $name, string $shortName) => Opd::create(['name' => $name, 'short_name' => $shortName])
        );
        $villages = collect(range(1, 8))->map(fn (int $n) => Village::create(['name' => "Kampung Contoh {$n}"]));

        $admin = User::create([
            'name' => 'Admin Distrik (contoh)',
            'username' => 'admin',
            'email' => 'admin@contoh.test',
            'password' => 'password',
            'must_change_password' => false,
            'role' => Role::Admin,
        ]);

        $opdUsers = $opds->map(fn (Opd $opd, string $shortName) => User::create([
            'name' => "Petugas {$opd->name} (contoh)",
            'username' => 'opd.'.strtolower($shortName),
            'password' => 'password',
            'must_change_password' => false,
            'role' => Role::Opd,
            'opd_id' => $opd->id,
        ]));

        $types = InstitutionType::pluck('id', 'code');
        $sectors = BusinessSector::pluck('id', 'name');

        $organizations = [];
        foreach (range(1, self::ORGANIZATION_COUNT) as $n) {
            $organizations[$n] = $this->seedOrganization($n, $admin, $types, $sectors, $villages, $opds);
        }

        // kelompok1 dan kelompok2 dibina OPD yang berbeda, untuk uji akses silang.
        $groupUsers = [
            1 => User::create([
                'name' => 'Ketua Contoh 01',
                'username' => 'kelompok1',
                // Email contoh untuk mencoba "Lupa kata sandi?" (email masuk ke Mailpit).
                'email' => 'kelompok1@contoh.test',
                'password' => 'password',
                'must_change_password' => false,
                'role' => Role::Kelompok,
                'organization_id' => $organizations[1]['organization']->id,
            ]),
            5 => User::create([
                'name' => 'Ketua Contoh 05',
                'username' => 'kelompok2',
                'password' => 'password',
                'must_change_password' => false,
                'role' => Role::Kelompok,
                'organization_id' => $organizations[5]['organization']->id,
            ]),
        ];

        // Teks contoh Konten Website; di produksi dibiarkan kosong sampai diisi Admin.
        SiteSetting::put([
            'home_intro' => 'Contoh pengantar: daftar kelompok usaha, koperasi, dan BUMDes di Distrik Ransiki beserta produknya. Ganti dengan teks resmi.',
            'profile_body' => "Contoh teks profil Distrik Ransiki. Ganti dengan teks resmi dari kantor distrik.\n\nParagraf kedua contoh: gambaran wilayah, kampung, dan kegiatan ekonomi masyarakat.",
            'economic_potential' => "Contoh teks potensi ekonomi:\n\n- Perkebunan kakao dan kopi\n- Perikanan dan pengolahan hasil laut\n- Pengolahan sagu dan hasil hutan",
            'office_address' => 'Jalan Contoh No. 1, Distrik Ransiki',
            'office_phone' => '0800-0000-0000',
            'office_whatsapp' => '0800-0000-0000',
            'office_email' => 'distrik@contoh.test',
            'office_hours' => 'Senin–Jumat, 08.00–15.00 WIT (contoh)',
        ]);

        $this->seedProfileVerification($organizations, $opdUsers);
        $this->seedPosts($admin, $organizations);

        foreach ($organizations as $n => $seeded) {
            $this->seedReports(
                $n,
                $seeded['organization'],
                $seeded['products'],
                $opdUsers[$seeded['opds'][0]],
                $groupUsers[$n] ?? null,
            );
        }
    }

    /**
     * Sebagian profil sudah diverifikasi OPD pembinanya; kelompok 1 (akun kelompok1) lalu mengubah ketua dan
     * telepon sehingga masuk antrean "Profil menunggu verifikasi".
     */
    private function seedProfileVerification(array $organizations, Collection $opdUsers): void
    {
        foreach ($organizations as $n => $seeded) {
            if ($n % 3 !== 1) {
                continue;
            }

            $seeded['organization']->forceFill([
                'profile_status' => $n === 1 ? ProfileStatus::NeedsReview : ProfileStatus::Verified,
                'profile_verified_at' => Carbon::parse('2026-09-15 10:00'),
                'profile_verified_by' => $opdUsers[$seeded['opds'][0]]->id,
                'profile_changed_fields' => $n === 1 ? ['leader_name', 'phone'] : null,
                'profile_changed_at' => $n === 1 ? Carbon::parse('2026-10-05 09:30') : null,
            ])->saveQuietly();
        }
    }

    /** Berita contoh: tiga terbit, satu draf, satu terjadwal (tidak tampil publik). */
    private function seedPosts(User $admin, array $organizations): void
    {
        $posts = [
            ['Contoh berita: panen kakao kelompok binaan', 'kegiatan', '2026-09-20 09:00', 'published', [1, 2]],
            ['Contoh berita: pelatihan pengemasan produk olahan', 'kegiatan', '2026-09-05 10:00', 'published', [4]],
            ['Contoh berita: jadwal pelaporan bulanan kelompok', 'berita', '2026-08-28 08:00', 'published', []],
            ['Contoh berita: draf yang belum diterbitkan', 'berita', null, 'draft', []],
            ['Contoh berita: pengumuman terjadwal', 'berita', '2027-01-05 08:00', 'published', []],
        ];

        foreach ($posts as [$title, $category, $publishedAt, $status, $related]) {
            $post = new Post([
                'title' => $title,
                'slug' => Post::uniqueSlug($title),
                'category' => $category,
                'summary' => 'Ringkasan contoh. Ganti dengan berita resmi dari kantor distrik.',
                'body' => "Ini teks **contoh**, bukan berita resmi.\n\n## Subjudul contoh\n\n- Butir pertama\n- Butir kedua\n\nParagraf penutup contoh.",
                'status' => $status,
                'published_at' => $publishedAt ? Carbon::parse($publishedAt) : null,
            ]);
            $post->forceFill(['created_by' => $admin->id, 'updated_by' => $admin->id])->save();
            $post->organizations()->sync(collect($related)->map(fn (int $n) => $organizations[$n]['organization']->id)->all());
        }
    }

    /**
     * @return array{organization: Organization, opds: list<string>, products: Collection}
     */
    private function seedOrganization(int $n, User $admin, Collection $types, Collection $sectors, Collection $villages, Collection $opds): array
    {
        $type = self::TYPES[$n - 1];
        $status = match (true) {
            in_array($n, self::CLOSED) => OrganizationStatus::Closed,
            in_array($n, self::INACTIVE) => OrganizationStatus::Inactive,
            default => OrganizationStatus::Active,
        };
        $members = $n % 9 === 0 ? null : 8 + ($n * 7) % 30;
        $legal = [LegalStatus::Registered, LegalStatus::InProcess, LegalStatus::None, LegalStatus::Unknown][$n % 4];
        $village = ($n - 1) % 8;

        $organization = Organization::create([
            'name' => sprintf('%s Contoh %02d', MasterDataSeeder::INSTITUTION_TYPES[$type], $n),
            'institution_type_id' => $types[$type],
            'village_id' => $villages[$village]->id,
            'address' => sprintf('Jalan Contoh No. %d, Kampung Contoh %d', $n, $village + 1),
            'founded_year' => 2010 + $n % 14,
            'leader_name' => sprintf('Ketua Contoh %02d', $n),
            'phone' => sprintf('0800-0000-%04d', $n),
            'email' => $n % 6 === 0 ? sprintf('kelompok%02d@contoh.test', $n) : null,
            'members_count' => $members,
            'workforce_count' => $members === null ? null : intdiv($members, 2),
            'description' => 'Contoh deskripsi kegiatan usaha kelompok. Data ini bukan data resmi.',
            'production_capacity' => $n % 3 === 0 ? null : 'Contoh kapasitas produksi per bulan.',
            'markets' => $n % 2 === 0 ? 'Pasar lokal distrik dan kampung sekitar.' : null,
            'legal_status' => $legal,
            'legal_number' => $legal === LegalStatus::Registered ? sprintf('CONTOH-%04d', $n) : null,
            'legal_notes' => $legal === LegalStatus::InProcess ? 'Contoh catatan: berkas sedang dilengkapi.' : null,
            'status' => $status,
            'reporting_starts_on' => in_array($n, self::REPORTING_FROM_SEPTEMBER) ? '2026-09-01' : self::HISTORY_PERIODS[0],
            'is_public' => $status === OrganizationStatus::Active && ! in_array($n, self::NOT_PUBLIC),
            'show_contact_publicly' => $n % 4 === 1,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $primarySector = self::PRIMARY_SECTOR[$type];
        $sectorNames = collect([$primarySector])
            ->merge($this->rotate(self::EXTRA_SECTORS, $n))
            ->unique()
            ->take(1 + $n % 3);
        $organization->sectors()->attach($sectorNames->map(fn (string $name) => $sectors[$name])->all());

        $opdKeys = in_array($n, self::FISHERY_ONLY)
            ? ['Perikanan']
            : array_slice($this->rotate(['Pertanian', 'DPMPTSP', 'Dinkopdag'], $n - 1), 0, 1 + $n % 3);
        $organization->opds()->attach(collect($opdKeys)->map(fn (string $key) => $opds[$key]->id)->all());

        $catalog = self::PRODUCTS[$primarySector];
        $products = collect(array_slice($catalog, 0, min(1 + $n % 2, count($catalog))))
            ->map(fn (array $product, int $index) => $organization->products()->create([
                'name' => $product[0],
                'description' => 'Contoh deskripsi produk.',
                'unit' => $product[1],
                'capacity' => sprintf('%d %s/bulan', 50 + $n * 10, $product[1]),
                'is_public' => $organization->is_public,
                'is_featured' => $organization->is_public && $index === 0 && $n % 3 === 0,
            ]));

        if ($n % 3 !== 0) {
            $licenseStatus = LicenseStatus::cases()[$n % 4];
            $organization->licenses()->create([
                'type' => ['NIB', 'PIRT', 'Sertifikat Halal'][$n % 3],
                'number' => $licenseStatus === LicenseStatus::Issued ? sprintf('CONTOH-IZIN-%04d', $n) : null,
                'status' => $licenseStatus,
                'submitted_on' => $licenseStatus === LicenseStatus::None ? null : '2026-06-15',
                'issued_on' => $licenseStatus === LicenseStatus::Issued ? '2026-08-20' : null,
                // Contoh tanggal habis berlaku: sebagian dekat (muncul di "Akan habis dalam 60 hari"), satu kosong.
                'expires_on' => match (true) {
                    $licenseStatus !== LicenseStatus::Issued => null,
                    $n % 3 === 1 => now()->addDays(20 + $n)->toDateString(),
                    $n > 10 => null,
                    default => '2029-08-20',
                },
                'notes' => $licenseStatus === LicenseStatus::NeedsFix ? 'Contoh catatan: dokumen pendukung perlu diperbaiki.' : null,
            ]);
        }

        return ['organization' => $organization, 'opds' => $opdKeys, 'products' => $products];
    }

    private function seedReports(int $n, Organization $organization, Collection $products, User $verifier, ?User $groupUser): void
    {
        if ($organization->status === OrganizationStatus::Closed) {
            return;
        }

        foreach ([...self::HISTORY_PERIODS, ...self::PERIODS] as $index => $period) {
            if ($organization->reporting_starts_on->gt(Carbon::parse($period))) {
                continue;
            }

            // $k < 0 = bulan riwayat: terverifikasi, sebagian kecil sengaja tidak melapor (celah di grafik).
            $k = $index - count(self::HISTORY_PERIODS);
            $step = $k >= 0 ? $k : $index % 4;
            $status = $k >= 0 ? $this->reportStatus($n, $k) : (($n + $index) % 9 === 0 ? null : ReportStatus::Verified);
            if ($status === null) {
                continue;
            }

            $noActivity = $organization->status === OrganizationStatus::Inactive || ($n === 3 && $k === 1);
            $sales = $noActivity ? null : ($k >= 0 ? 150_000 * $n + 50_000 * $k : 120_000 * $n + 40_000 * $step);

            // Sebagian angka sengaja dibiarkan NULL (tidak diisi) agar keterangan "dari X kelompok" teruji.
            $report = $organization->monthlyReports()->create([
                'period' => $period,
                'status' => $status,
                'no_activity' => $noActivity,
                'activity_summary' => $noActivity ? null : 'Contoh ringkasan kegiatan bulan '.Format::month($period).'.',
                'activity_count' => $noActivity ? 0 : 1 + ($n + $step) % 5,
                'achievements' => $noActivity || $n % 2 === 0 ? null : 'Contoh capaian: produksi berjalan sesuai rencana.',
                'members_active' => $noActivity || $organization->members_count === null ? null : max(1, $organization->members_count - $step),
                'workforce_count' => $noActivity ? null : $organization->workforce_count,
                'members_change' => $noActivity ? null : [0, 1, 0, -1][($n + $step) % 4],
                'income_sales' => $sales,
                'income_other' => $noActivity || $n % 3 === 0 ? null : ($n % 2) * 100_000,
                'costs' => $noActivity || $n % 5 === 4 ? null : (int) round($sales * 0.6, -3),
            ]);

            if (! $noActivity && $products->isNotEmpty()) {
                $product = $products->first();
                $produced = 20 + $n + 5 * $step;
                $sold = $produced - 5;
                $report->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'production_qty' => $produced,
                    'production_unit' => $product->unit,
                    'sales_qty' => $sold,
                    'sales_unit' => $product->unit,
                    'revenue' => $sold * 12_000,
                ]);
            }

            if (! $noActivity && $n % 3 === 0) {
                $report->issues()->create([
                    'kind' => IssueKind::Problem,
                    'category' => ProblemCategory::Modal->value,
                    'description' => 'Contoh masalah: modal kerja terbatas.',
                ]);
            }

            if (! $noActivity && $n % 4 === 1) {
                $report->issues()->create([
                    'kind' => IssueKind::Support,
                    'category' => SupportCategory::Pelatihan->value,
                    'description' => 'Contoh kebutuhan: pelatihan pengemasan produk.',
                ]);
            }

            $this->seedHistory($report, $status, $n, Carbon::parse($period), $verifier, $groupUser);
        }
    }

    /**
     * Status laporan contoh; null berarti kelompok sengaja belum melapor.
     */
    private function reportStatus(int $n, int $k): ?ReportStatus
    {
        if (($n + $k) % 7 === 0) {
            return null;
        }

        return match ($k) {
            0 => $n % 10 === 3 ? ReportStatus::Submitted : ReportStatus::Verified,
            1 => match ($n % 5) {
                1 => ReportStatus::Revision,
                2 => ReportStatus::Submitted,
                default => ReportStatus::Verified,
            },
            default => $n % 6 === 5 ? ReportStatus::Revision : match ($n % 4) {
                0 => null,
                1 => ReportStatus::Draft,
                2 => ReportStatus::Submitted,
                default => ReportStatus::Verified,
            },
        };
    }

    private function seedHistory(MonthlyReport $report, ReportStatus $status, int $n, Carbon $period, User $verifier, ?User $groupUser): void
    {
        $submittedAt = $period->copy()->addMonth()->setDay(2 + $n % 5)->setTime(10, 0);
        $revisionNote = 'Contoh catatan: lengkapi angka penjualan dan biaya.';

        $steps = match ($status) {
            ReportStatus::Draft => [],
            ReportStatus::Submitted => [
                [ReviewAction::Submitted, $submittedAt, $groupUser, null],
            ],
            ReportStatus::Revision => [
                [ReviewAction::Submitted, $submittedAt, $groupUser, null],
                [ReviewAction::RevisionRequested, $submittedAt->copy()->addDay(), $verifier, $revisionNote],
            ],
            ReportStatus::Verified => $n % 4 === 2
                ? [
                    [ReviewAction::Submitted, $submittedAt, $groupUser, null],
                    [ReviewAction::RevisionRequested, $submittedAt->copy()->addDay(), $verifier, $revisionNote],
                    [ReviewAction::Submitted, $submittedAt->copy()->addDays(2), $groupUser, null],
                    [ReviewAction::Verified, $submittedAt->copy()->addDays(3), $verifier, null],
                ]
                : [
                    [ReviewAction::Submitted, $submittedAt, $groupUser, null],
                    [ReviewAction::Verified, $submittedAt->copy()->addDays(2), $verifier, null],
                ],
        };

        // submitted_at = pengajuan pertama (dasar status terlambat); pengajuan ulang hanya di riwayat.
        $firstSubmitted = null;
        $verified = null;
        foreach ($steps as [$action, $at, $user, $note]) {
            ReportReview::forceCreate([
                'monthly_report_id' => $report->id,
                'user_id' => $user?->id,
                'action' => $action,
                'note' => $note,
                'created_at' => $at,
            ]);

            if ($action === ReviewAction::Submitted) {
                $firstSubmitted ??= [$at, $user];
            }
            if ($action === ReviewAction::Verified) {
                $verified = [$at, $user];
            }
        }

        if ($firstSubmitted) {
            $report->forceFill([
                'submitted_at' => $firstSubmitted[0],
                'submitted_by' => $firstSubmitted[1]?->id,
                'verified_at' => $verified[0] ?? null,
                'verified_by' => $verified ? $verified[1]->id : null,
            ])->save();
        }
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function rotate(array $items, int $offset): array
    {
        $offset %= count($items);

        return [...array_slice($items, $offset), ...array_slice($items, 0, $offset)];
    }
}
