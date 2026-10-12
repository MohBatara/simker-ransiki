<?php

namespace Database\Seeders;

use App\Enums\FollowUpStatus;
use App\Enums\IssueKind;
use App\Enums\LegalStatus;
use App\Enums\LicenseStatus;
use App\Enums\OrganizationStatus;
use App\Enums\ProfileStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Enums\Role;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\IssueFollowUp;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Product;
use App\Models\ReportReview;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Village;
use App\Support\Format;
use App\Support\ImageProcessor;
use App\Support\ImageVariants;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Data demo untuk pengembangan dan pemolesan tampilan (CLAUDE.md Bagian 10). Isinya di DemoData; semua
 * nama, nomor, dan email fiktif. Tidak memakai angka acak: setiap variasi diturunkan dari crc32 nomor
 * kelompok + bulan, sehingga migrate:fresh --seed selalu menghasilkan data yang sama.
 */
class DemoSeeder extends Seeder
{
    /** Bulan laporan pertama; 12 bulan sampai bulan wajib lapor (September 2026). */
    private const FIRST_PERIOD = '2025-10-01';

    /** 12 bulan riwayat + bulan berjalan (Oktober 2026, hanya draf). */
    private const PERIOD_COUNT = 13;

    /** Status laporan yang dipaksa agar akun uji punya keadaan yang menarik untuk dicoba. */
    private const OVERRIDES = [
        1 => ['2026-08' => ReportStatus::Verified, '2026-09' => ReportStatus::Revision, '2026-10' => ReportStatus::Draft],
        5 => ['2026-09' => ReportStatus::Draft],
    ];

    private const REVISION_NOTES = [
        'Angka penjualan belum sesuai dengan jumlah produksi. Mohon dicek lagi.',
        'Lengkapi biaya produksi bulan ini, termasuk ongkos angkut.',
        'Satuan produk tertulis berbeda dengan bulan lalu. Mohon diseragamkan.',
        'Foto kegiatan belum dilampirkan. Mohon tambahkan satu atau dua foto.',
    ];

    private Carbon $now;

    public function run(): void
    {
        // Akun uji berkata sandi "password": menolak dengan error (bukan diam-diam dilewati) agar
        // db:seed --class=DemoSeeder di server gagal dengan jelas.
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoSeeder hanya untuk environment local. Di server gunakan db:seed --class=ProductionSeeder.');
        }

        $this->now = now();
        $this->call(MasterDataSeeder::class);

        // Deskripsi contoh hanya di data demo; ProductionSeeder membiarkannya kosong sampai diisi Admin.
        foreach (DemoData::INSTITUTION_TYPE_TEXT as $code => $description) {
            InstitutionType::where('code', $code)->update(['description' => $description]);
        }

        $opds = collect(DemoData::OPDS)->map(
            fn (string $name, string $shortName) => Opd::create(['name' => $name, 'short_name' => $shortName])
        );
        $villages = collect(DemoData::VILLAGES)->map(fn (string $name) => Village::create(['name' => $name]));

        $admin = User::create([
            'name' => 'Maria Wonatorey',
            'username' => 'admin',
            'email' => 'admin@example.test',
            'phone' => '0812-0000-0001',
            'password' => 'password',
            'must_change_password' => false,
            'role' => Role::Admin,
        ]);

        $opdUsers = $opds->map(fn (Opd $opd, string $shortName) => User::create([
            'name' => DemoData::OPD_OFFICERS[$shortName],
            'username' => 'opd.'.strtolower($shortName),
            'email' => 'opd.'.strtolower($shortName).'@example.test',
            'password' => 'password',
            'must_change_password' => false,
            'role' => Role::Opd,
            'opd_id' => $opd->id,
        ]));

        $types = InstitutionType::pluck('id', 'code');
        $sectors = BusinessSector::pluck('id', 'name');

        $organizations = [];
        foreach (DemoData::ORGANIZATIONS as $n => $data) {
            $organizations[$n] = $this->seedOrganization($n, $data, $admin, $types, $sectors, $villages, $opds);
        }

        // kelompok1 dan kelompok2 dibina OPD yang berbeda, untuk uji akses silang.
        $groupUsers = [
            1 => User::create([
                'name' => DemoData::ORGANIZATIONS[1]['leader'],
                'username' => 'kelompok1',
                // Untuk mencoba "Lupa kata sandi?" (email masuk ke Mailpit).
                'email' => 'kelompok1@example.test',
                'password' => 'password',
                'must_change_password' => false,
                'role' => Role::Kelompok,
                'organization_id' => $organizations[1]['organization']->id,
            ]),
            5 => User::create([
                'name' => DemoData::ORGANIZATIONS[5]['leader'],
                'username' => 'kelompok2',
                'password' => 'password',
                'must_change_password' => false,
                'role' => Role::Kelompok,
                'organization_id' => $organizations[5]['organization']->id,
            ]),
        ];

        // Konten Website demo; di produksi dibiarkan kosong sampai diisi Admin.
        SiteSetting::put([
            'home_intro' => 'Direktori kelompok usaha, koperasi, dan BUMDes di Distrik Ransiki beserta produk dan perkembangannya setiap bulan.',
            'profile_body' => "Distrik Ransiki berada di Kabupaten Manokwari Selatan, Papua Barat. Sebagian besar warga hidup dari kebun, laut, dan usaha olahan rumahan.\n\nHalaman ini memuat gambaran umum wilayah dan kegiatan ekonomi masyarakat. Teks resmi akan diisi kantor distrik.",
            'economic_potential' => "- Perkebunan kakao, kopi, pinang, dan kelapa\n- Perikanan tangkap, kepiting bakau, dan ikan asap\n- Pengolahan sagu, pisang, dan hasil kebun lain\n- Kerajinan noken dan anyaman",
            'office_address' => 'Kantor Distrik Ransiki, Kabupaten Manokwari Selatan',
            'office_phone' => '0812-0000-0002',
            'office_whatsapp' => '0812-0000-0003',
            'office_email' => 'distrik@example.test',
            'office_hours' => 'Senin sampai Jumat, pukul 08.00 sampai 15.00 WIT',
        ]);

        if ($this->photosEnabled()) {
            $this->seedSitePhotos();
        }

        $this->seedProfileVerification($organizations, $opdUsers);
        $this->seedPosts($admin, $organizations);

        foreach ($organizations as $n => $seeded) {
            $this->seedReports($n, DemoData::ORGANIZATIONS[$n], $seeded, $opdUsers, $groupUsers[$n] ?? null);
        }
    }

    /**
     * Foto hanya diproses di local: tes tidak menulis ke storage asli dan tetap cepat. Sumbernya foto
     * sementara di database/seeders/photos; path tetap (…-demo.jpg) agar seed ulang menimpa file yang sama.
     */
    private function photosEnabled(): bool
    {
        return app()->isLocal() && is_dir(database_path('seeders/photos'));
    }

    private function photo(string $file): string
    {
        return database_path('seeders/photos/'.$file);
    }

    private function storeWidePhoto(string $file, string $path, array $budgets = []): string
    {
        ImageVariants::store('local', $this->photo($file), $path, ImageVariants::WIDE, $budgets);

        return $path;
    }

    private function storeProductPhoto(Product $product, string $file): void
    {
        $base = sprintf('products/%d/demo', $product->id);

        ImageVariants::store(Product::PHOTO_DISK, $this->photo($file), $base.'.jpg', ImageVariants::PRODUCT);
        $thumbnail = ImageProcessor::process($this->photo($file), 'image/jpeg', ImageProcessor::THUMBNAIL_SIDE, 'jpeg');
        Storage::disk(Product::PHOTO_DISK)->put($base.Product::THUMBNAIL_SUFFIX.'.jpg', $thumbnail['contents']);

        $product->forceFill(['photo_path' => $base.'.jpg'])->save();
    }

    /** Foto sampul, "Tentang", dan lima bidang usaha. Perdagangan dan Lainnya sengaja tanpa foto. */
    private function seedSitePhotos(): void
    {
        SiteSetting::put([
            'cover_photo_path' => $this->storeWidePhoto('sampul.jpg', 'site/sampul-demo.jpg', ImageVariants::HERO_BUDGET),
            'about_photo_path' => $this->storeWidePhoto('panen-kakao.jpg', 'site/tentang-demo.jpg'),
            // Hanya keterangan isi foto; catatan internal (mis. "foto sementara") tidak pernah tampil di publik.
            'cover_caption' => 'Kebun kakao di pesisir.',
            'cover_credit' => null,
        ]);

        $files = [
            'Pertanian' => 'pertanian.jpg',
            'Perkebunan' => 'kakao.jpg',
            'Perikanan' => 'perikanan.jpg',
            'UMKM' => 'keripik.jpg',
            'Pengolahan Hasil' => 'pengemasan.jpg',
        ];

        foreach (BusinessSector::whereIn('name', array_keys($files))->get() as $sector) {
            $path = $this->storeWidePhoto($files[$sector->name], "site/bidang-{$sector->id}-demo.jpg");
            $sector->forceFill(['photo_path' => $path])->save();
        }
    }

    /**
     * Sebagian profil sudah diverifikasi OPD pembinanya; kelompok 1 (akun kelompok1) lalu mengubah ketua dan
     * telepon sehingga masuk antrean "Profil menunggu verifikasi".
     */
    private function seedProfileVerification(array $organizations, Collection $opdUsers): void
    {
        foreach ($organizations as $n => $seeded) {
            if ($n % 3 !== 1 || $seeded['organization']->status === OrganizationStatus::Closed) {
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

    /** Enam berita terbit (empat berfoto), satu draf, dan satu terjadwal (keduanya tidak tampil publik). */
    private function seedPosts(User $admin, array $organizations): void
    {
        $posts = [
            [
                'Pelatihan fermentasi biji kakao untuk tiga kelompok tani', 'kegiatan', '2026-09-24 09:00', 'published', [1, 19, 38], 'panen-kakao.jpg',
                'Petugas penyuluh melatih anggota tiga kelompok tani cara fermentasi dan penjemuran biji kakao agar mutunya seragam.',
                "Pelatihan berlangsung dua hari di rumah fermentasi Kelompok Tani Kakao Harapan. Peserta berasal dari tiga kelompok tani kakao binaan.\n\n## Materi\n\n- Memilih buah yang matang penuh\n- Fermentasi dalam kotak kayu selama lima hari\n- Penjemuran di para-para sampai kadar air cukup rendah\n- Sortasi dan pengemasan dalam karung bersih\n\nSetiap kelompok membawa pulang satu set catatan praktik. Petugas akan berkunjung lagi bulan depan untuk melihat hasilnya.",
            ],
            [
                'Produk olahan kelompok tampil di pameran UMKM kabupaten', 'kegiatan', '2026-08-19 10:00', 'published', [2, 20, 34, 36], 'pengemasan.jpg',
                'Keripik pisang, cokelat batang, dan sagu lempeng dari empat kelompok binaan dipajang dan dijual di pameran UMKM tingkat kabupaten.',
                "Empat kelompok usaha dari Distrik Ransiki ikut pameran UMKM selama tiga hari. Stan distrik menampilkan keripik pisang, cokelat batang, sagu lempeng, dan bagea.\n\nSebagian besar produk habis terjual pada hari kedua. Beberapa pengunjung meminta kontak kelompok untuk pesanan rutin.\n\nPengalaman ini menunjukkan kemasan dan label yang rapi sangat membantu penjualan.",
            ],
            [
                'Panen raya kakao di kebun kelompok tani', 'kegiatan', '2026-07-08 08:00', 'published', [19], 'kakao.jpg',
                'Kelompok Tani Kakao Mekar Jaya memanen kakao bersama setelah tiga bulan perawatan kebun dan pemangkasan.',
                "Panen raya dilakukan bergiliran di kebun anggota selama satu pekan. Hasilnya dikumpulkan di rumah fermentasi kelompok.\n\nAnggota mencatat berat hasil panen setiap kebun agar pembagian hasil penjualan adil.",
            ],
            [
                'Pelatihan pengemasan dan label untuk produk keripik', 'kegiatan', '2026-06-12 09:00', 'published', [2, 14, 20], 'keripik.jpg',
                'Pelaku usaha keripik belajar memilih kemasan yang tahan lama dan menulis label yang lengkap.',
                "Peserta mempraktikkan pengemasan dengan plastik tebal dan perekat panas. Materi label mencakup nama produk, berat bersih, tanggal produksi, dan alamat usaha.\n\nKelompok yang sudah siap akan didampingi mengurus izin PIRT.",
            ],
            [
                'Batas pengiriman laporan bulanan setiap tanggal 10', 'berita', '2026-09-01 08:00', 'published', [], null,
                'Kelompok diminta mengirim laporan bulanan paling lambat tanggal 10 bulan berikutnya pukul 23.59 WIT.',
                "Laporan bulanan berisi kegiatan, produksi dan penjualan, keuangan, masalah, dan kebutuhan dukungan.\n\nKelompok yang belum punya akun dapat melapor lewat petugas OPD pembina. Bila tidak ada kegiatan, pilih \"Tidak ada aktivitas bulan ini\".",
            ],
            [
                'Pendampingan pengurusan NIB bagi kelompok usaha', 'berita', '2026-08-05 08:00', 'published', [], null,
                'Petugas membuka jadwal pendampingan pengurusan Nomor Induk Berusaha di kantor distrik setiap Selasa dan Kamis.',
                "Bawa KTP ketua, data anggota, dan keterangan usaha. Pendampingan tidak dipungut biaya.\n\nKelompok yang sudah punya NIB dapat melanjutkan ke pengurusan PIRT atau sertifikat halal.",
            ],
            [
                'Rencana pelatihan pengolahan ikan asap', 'kegiatan', null, 'draft', [6, 33], null,
                'Rencana pelatihan pengasapan ikan yang lebih bersih untuk kelompok nelayan.',
                "Draf. Jadwal dan tempat menyusul.",
            ],
            [
                'Pameran produk kampung akhir tahun', 'berita', '2026-12-01 08:00', 'published', [], null,
                'Pendaftaran stan pameran produk kampung akhir tahun dibuka untuk kelompok binaan.',
                "Setiap kelompok mendapat satu meja. Pendaftaran lewat petugas OPD pembina.",
            ],
        ];

        foreach ($posts as [$title, $category, $publishedAt, $status, $related, $photo, $summary, $body]) {
            $post = new Post([
                'title' => $title,
                'slug' => Post::uniqueSlug($title),
                'category' => $category,
                'summary' => $summary,
                'body' => $body,
                'status' => $status,
                'published_at' => $publishedAt ? Carbon::parse($publishedAt) : null,
            ]);
            $post->forceFill(['created_by' => $admin->id, 'updated_by' => $admin->id])->save();
            $post->organizations()->sync(collect($related)->map(fn (int $n) => $organizations[$n]['organization']->id)->all());

            if ($photo && $this->photosEnabled()) {
                $path = "posts/berita-{$post->id}-demo.jpg";
                ImageVariants::store(Post::PHOTO_DISK, $this->photo($photo), $path, ImageVariants::WIDE, ImageVariants::HERO_BUDGET);
                $post->forceFill(['cover_photo_path' => $path])->save();
            }
        }
    }

    /**
     * @return array{organization: Organization, opds: list<string>, products: list<array{0: Product, 1: int, 2: int, 3: string}>}
     */
    private function seedOrganization(int $n, array $data, User $admin, Collection $types, Collection $sectors, Collection $villages, Collection $opds): array
    {
        $status = OrganizationStatus::from($data['status'] ?? 'active');
        $village = $villages[$data['village']];
        $members = $data['members'];
        $primarySector = $data['sectors'][0];
        $firstProduct = $data['products'][0];
        $productList = collect($data['products'])->map(fn (array $product) => mb_strtolower($product[0]))->join(', ', ' dan ');

        $legal = in_array($data['type'], ['bumdes', 'kdmp', 'knmp'])
            ? LegalStatus::Registered
            : [LegalStatus::Registered, LegalStatus::InProcess, LegalStatus::None, LegalStatus::Unknown][$n % 4];

        $organization = Organization::create([
            'name' => $data['name'],
            'institution_type_id' => $types[$data['type']],
            'village_id' => $village->id,
            'address' => $n % 8 === 7 ? null : sprintf('RT %03d, %s', 1 + $n % 6, $village->name),
            'founded_year' => $data['founded'],
            'leader_name' => $data['leader'],
            'phone' => $n % 7 === 0 ? null : sprintf('0812-0000-%04d', 1000 + $n),
            'email' => ($data['email'] ?? false) ? "kelompok{$n}@example.test" : null,
            'members_count' => $members,
            'workforce_count' => $members === null || $n % 5 === 0 ? null : max(1, intdiv($members, 3)),
            'description' => $n % 11 === 0 ? null : strtr(DemoData::SECTOR_TEXT[$primarySector][0], ['{produk}' => $productList, '{kampung}' => $village->name]),
            'production_capacity' => $n % 3 === 0 ? null : sprintf('Sekitar %s %s per bulan', Format::number(round($firstProduct[2] * 1.3, -1)), $firstProduct[1]),
            'markets' => [
                'Pasar distrik dan pengepul dari kota.',
                'Kios di kampung, pasar mingguan, dan pesanan langsung.',
                null,
                'Pengepul, rumah makan di distrik, dan pembeli dari luar daerah lewat pesanan.',
            ][$n % 4],
            'assets' => $n % 2 === 0 ? null : 'Tanah dan bangunan rumah produksi milik bersama.',
            'facilities' => $n % 3 === 2 ? null : 'Gudang kecil, para-para jemur, dan tempat pertemuan anggota.',
            'equipment' => $n % 4 === 3 ? null : 'Timbangan duduk, terpal, dan alat kemas sederhana.',
            'legal_status' => $legal,
            'legal_number' => $legal === LegalStatus::Registered ? sprintf('AHU-FIKTIF-%04d', $n) : null,
            'legal_notes' => $legal === LegalStatus::InProcess ? 'Akta pendirian sedang disiapkan; berkas anggota masih dilengkapi.' : null,
            'status' => $status,
            'reporting_starts_on' => $data['starts'] ?? self::FIRST_PERIOD,
            'is_public' => $status !== OrganizationStatus::Closed && ($data['public'] ?? true),
            'show_contact_publicly' => $n % 3 === 1,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $organization->sectors()->attach(collect($data['sectors'])->map(fn (string $name) => $sectors[$name])->all());
        $organization->opds()->attach(collect($data['opds'])->map(fn (string $key) => $opds[$key]->id)->all());

        $descriptions = [
            'Hasil anggota kelompok, dijual per {satuan}.',
            'Dikemas rapi dan siap dikirim. Dijual per {satuan}.',
            'Dibuat setiap pekan sesuai pesanan.',
            'Dipilih dan disortir sebelum dijual.',
            null,
        ];

        $products = [];
        foreach ($data['products'] as $index => [$name, $unit, $base, $price, $season, $photo]) {
            $description = $descriptions[($n + $index) % count($descriptions)];
            $product = $organization->products()->create([
                'name' => $name,
                'description' => $description ? str_replace('{satuan}', $unit, $description) : null,
                'unit' => $unit,
                'capacity' => sprintf('%s %s/bulan', Format::number(round($base * 1.2, -1)), $unit),
                'is_public' => $organization->is_public && ! ($index > 0 && $n % 4 === 0),
                'is_featured' => $organization->is_public && $index === 0 && ($photo !== null || $n % 4 === 2),
            ]);

            if ($photo && $this->photosEnabled()) {
                $this->storeProductPhoto($product, $photo);
            }

            $products[] = [$product, $base, $price, $season];
        }

        $this->seedLicenses($n, $organization);

        return ['organization' => $organization, 'opds' => $data['opds'], 'products' => $products];
    }

    /** 0–2 izin per kelompok dengan status beragam; sebagian izin terbit akan habis dalam 60 hari. */
    private function seedLicenses(int $n, Organization $organization): void
    {
        $types = ['NIB' => 'NIB', 'PIRT' => 'PIRT', 'Sertifikat Halal' => 'HALAL'];

        foreach (range(0, $n % 3 - 1) as $i) {
            if ($n % 3 === 0) {
                break;
            }

            $type = array_keys($types)[($n + $i) % 3];
            $status = LicenseStatus::cases()[($n + $i) % 4];

            $organization->licenses()->create([
                'type' => $type,
                'number' => $status === LicenseStatus::Issued ? sprintf('FIKTIF-%s-%04d', $types[$type], $n) : null,
                'status' => $status,
                'submitted_on' => $status === LicenseStatus::None ? null : Carbon::parse('2026-03-02')->addDays(($n * 9) % 120)->toDateString(),
                'issued_on' => $status === LicenseStatus::Issued ? Carbon::parse('2026-06-01')->addDays(($n * 7) % 60)->toDateString() : null,
                'expires_on' => match (true) {
                    $status !== LicenseStatus::Issued => null,
                    $n % 3 === 1 => $this->now->copy()->addDays(15 + $n % 40)->toDateString(),
                    $n > 25 => null,
                    default => '2029-08-20',
                },
                'notes' => $status === LicenseStatus::NeedsFix ? 'Foto tempat produksi dan surat keterangan kampung perlu diunggah ulang.' : null,
            ]);
        }
    }

    /**
     * @param  array{organization: Organization, opds: list<string>, products: list<array>}  $seeded
     */
    private function seedReports(int $n, array $data, array $seeded, Collection $opdUsers, ?User $groupUser): void
    {
        $organization = $seeded['organization'];
        if ($organization->status === OrganizationStatus::Closed) {
            return;
        }

        $verifier = $opdUsers[$seeded['opds'][0]];
        $tier = $data['tier'];
        $inactiveSince = isset($data['since']) ? Carbon::parse($data['since']) : null;
        $primarySector = $data['sectors'][0];

        foreach (range(0, self::PERIOD_COUNT - 1) as $i) {
            $period = Carbon::parse(self::FIRST_PERIOD)->addMonthsNoOverflow($i);
            if ($organization->reporting_starts_on->gt($period)) {
                continue;
            }

            $status = array_key_exists($period->format('Y-m'), self::OVERRIDES[$n] ?? [])
                ? self::OVERRIDES[$n][$period->format('Y-m')]
                : $this->reportStatus($n, $tier, $i);
            if ($status === null) {
                continue;
            }

            [$status, $steps] = $this->timeline($status, $n, $tier, $i, $period, $verifier, $groupUser);

            // Tidak aktif sementara, atau nelayan tidak melaut saat angin barat bulan Januari.
            $noActivity = ($inactiveSince && $period->gte($inactiveSince))
                || ($primarySector === 'Perikanan' && $period->month === 1 && $tier !== 'rajin');

            [$figures, $items] = $noActivity
                ? [['activity_count' => 0], []]
                : $this->reportFigures($n, $i, $data, $seeded['products'], $organization, $period);

            $report = $organization->monthlyReports()->create([
                'period' => $period->toDateString(),
                'status' => $status,
                'no_activity' => $noActivity,
                ...$figures,
            ]);

            foreach ($items as $item) {
                $report->items()->create($item);
            }

            if (! $noActivity) {
                $this->seedIssues($report, $n, $i);
            }

            // Kelompok tanpa akun: draf bulan wajib lapor diisi petugas OPD kedua atas nama kelompok.
            if ($status === ReportStatus::Draft && ! $groupUser && $tier === 'telat' && count($seeded['opds']) > 1 && $i === 11) {
                $enteredBy = $opdUsers[$seeded['opds'][1]];
                $report->forceFill(['entered_by' => $enteredBy->id])->save();
                ReportReview::forceCreate([
                    'monthly_report_id' => $report->id,
                    'user_id' => $enteredBy->id,
                    'action' => ReviewAction::Entered,
                    'created_at' => Carbon::parse('2026-10-09 14:00'),
                ]);
            }

            $this->saveHistory($report, $steps);

            if (in_array($status, [ReportStatus::Submitted, ReportStatus::Verified])) {
                $this->seedFollowUps($report, $n, $i, $verifier, $steps[0][1]);
            }
        }
    }

    /**
     * Status awal per kebiasaan melapor; null = tidak melapor bulan itu. Indeks 0–8: Oktober 2025–Juni 2026,
     * 9: Juli, 10: Agustus, 11: September (bulan wajib lapor, tenggat 10 Oktober), 12: Oktober (bulan berjalan).
     */
    private function reportStatus(int $n, string $tier, int $i): ?ReportStatus
    {
        $r = $this->chance($n, $i, 'status');
        $gap = ['rajin' => 0.0, 'biasa' => 0.05, 'telat' => 0.18][$tier];

        return match (true) {
            $i === 12 => $tier === 'rajin' && $r < 0.5 ? ReportStatus::Draft : null,
            $i <= 8 => $i > 0 && $r < $gap ? null : ReportStatus::Verified,
            $i === 9 => match (true) {
                $r < $gap => null,
                $r < $gap + 0.05 => ReportStatus::Submitted,
                default => ReportStatus::Verified,
            },
            $i === 10 => match ($tier) {
                'rajin' => $r < 0.2 ? ReportStatus::Submitted : ReportStatus::Verified,
                'biasa' => $r < 0.1 ? ReportStatus::Revision : ($r < 0.25 ? ReportStatus::Submitted : ReportStatus::Verified),
                'telat' => $r < 0.2 ? null : ($r < 0.35 ? ReportStatus::Revision : ($r < 0.5 ? ReportStatus::Submitted : ReportStatus::Verified)),
            },
            default => match ($tier) {
                'rajin' => $r < 0.5 ? ReportStatus::Submitted : ($r < 0.85 ? ReportStatus::Verified : ReportStatus::Revision),
                'biasa' => match (true) {
                    $r < 0.12 => null,
                    $r < 0.3 => ReportStatus::Draft,
                    $r < 0.65 => ReportStatus::Submitted,
                    $r < 0.75 => ReportStatus::Revision,
                    default => ReportStatus::Verified,
                },
                'telat' => $r < 0.35 ? null : ($r < 0.7 ? ReportStatus::Draft : ReportStatus::Submitted),
            },
        };
    }

    /**
     * Riwayat pengajuan dan tinjauan. Tanggal pengajuan mengikuti kebiasaan kelompok (yang "telat" sering
     * lewat tanggal 10). Langkah yang jatuh setelah sekarang dibuang, dan status mengikuti langkah terakhir.
     *
     * @return array{0: ReportStatus, 1: list<array{0: ReviewAction, 1: Carbon, 2: ?User, 3: ?string}>}
     */
    private function timeline(ReportStatus $status, int $n, string $tier, int $i, Carbon $period, User $verifier, ?User $groupUser): array
    {
        if ($status === ReportStatus::Draft) {
            return [$status, []];
        }

        $r = $this->chance($n, $i, 'day');
        $late = $this->chance($n, $i, 'late');
        $day = match ($tier) {
            'rajin' => 1 + (int) ($r * 6),
            'biasa' => $late < 0.12 ? 11 + (int) ($r * 8) : 3 + (int) ($r * 8),
            'telat' => $late < 0.6 ? 11 + (int) ($r * 18) : 6 + (int) ($r * 5),
        };

        $submittedAt = $period->copy()->addMonthNoOverflow()->setDay($day)->setTime(7 + $n % 10, ($n * 7) % 60);
        if ($submittedAt->gte($this->now)) {
            $submittedAt = $this->now->copy()->subHours(3);
        }

        $review = fn (int $offset) => $submittedAt->copy()->addDays($offset)->setTime(9 + ($n + $i) % 6, ($n * 13) % 60);
        $note = self::REVISION_NOTES[($n + $i) % count(self::REVISION_NOTES)];
        $verifyDelay = 1 + (int) ($this->chance($n, $i, 'verify') * 4);

        $steps = [[ReviewAction::Submitted, $submittedAt, $groupUser, null]];
        $steps = match ($status) {
            ReportStatus::Submitted => $steps,
            ReportStatus::Revision => [...$steps, [ReviewAction::RevisionRequested, $review(1 + $n % 2), $verifier, $note]],
            default => $this->chance($n, $i, 'cycle') < 0.15
                ? [
                    ...$steps,
                    [ReviewAction::RevisionRequested, $review(1), $verifier, $note],
                    [ReviewAction::Submitted, $review(3)->subHours(2), $groupUser, null],
                    [ReviewAction::Verified, $review(3 + $verifyDelay), $verifier, null],
                ]
                : [...$steps, [ReviewAction::Verified, $review($verifyDelay), $verifier, null]],
        };

        $steps = array_values(array_filter($steps, fn (array $step) => $step[1]->lt($this->now)));
        $status = match (end($steps)[0]) {
            ReviewAction::Verified => ReportStatus::Verified,
            ReviewAction::RevisionRequested => ReportStatus::Revision,
            default => ReportStatus::Submitted,
        };

        return [$status, $steps];
    }

    /** @param  list<array{0: ReviewAction, 1: Carbon, 2: ?User, 3: ?string}>  $steps */
    private function saveHistory(MonthlyReport $report, array $steps): void
    {
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
     * Angka satu laporan: produksi mengikuti musim komoditas, keuangan dari penjualan. Kelompok "telat"
     * kadang tidak mengisi keuangan (NULL, laba ikut NULL), bukan nol.
     *
     * @param  list<array{0: Product, 1: int, 2: int, 3: string}>  $products
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function reportFigures(int $n, int $i, array $data, array $products, Organization $organization, Carbon $period): array
    {
        $tier = $data['tier'];
        $chance = fn (string $key) => $this->chance($n, $i, $key);
        $fillsFinance = ! ($tier === 'telat' && $chance('finance') < 0.3) && ! ($tier === 'biasa' && $chance('finance') < 0.06);
        $growth = 1 + 0.01 * $i;

        $items = [];
        foreach ($products as $index => [$product, $base, $price, $season]) {
            if ($index > 0 && $chance("skip{$index}") < 0.2) {
                continue;
            }

            $factor = DemoData::SEASONS[$season][$period->month - 1] * (0.85 + 0.3 * $chance("qty{$index}")) * $growth;
            $produced = $this->quantity($base * $factor, $product->unit);
            $sold = $this->quantity($produced * (0.8 + 0.2 * $chance("sold{$index}")), $product->unit);

            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'production_qty' => $produced,
                'production_unit' => $product->unit,
                'sales_qty' => $sold,
                'sales_unit' => $product->unit,
                'revenue' => $fillsFinance ? (int) round($sold * $price, -3) : null,
            ];
        }

        $sales = $fillsFinance ? array_sum(array_column($items, 'revenue')) : null;
        $isEnterprise = in_array($data['type'], ['bumdes', 'kdmp']);
        $members = $organization->members_count;
        $text = DemoData::SECTOR_TEXT[$data['sectors'][0]];
        $productName = mb_strtolower($products[0][0]->name);

        return [[
            'activity_summary' => str_replace('{produk}', $productName, $text[1][($n + $i) % count($text[1])]),
            'activity_count' => 2 + (int) ($chance('activities') * 7),
            'achievements' => $chance('achievement') < 0.3 ? $text[2][($n + $i) % count($text[2])] : null,
            'members_active' => $members === null || ($tier === 'telat' && $chance('members') < 0.2) ? null : max(1, $members - (int) ($chance('active') * 4)),
            'workforce_count' => $organization->workforce_count,
            'members_change' => $tier === 'telat' && $chance('members') < 0.2 ? null : match (true) {
                $chance('change') < 0.08 => 1,
                $chance('change') < 0.12 => -1,
                $chance('change') < 0.14 => 2,
                default => 0,
            },
            'income_sales' => $sales,
            'income_other' => match (true) {
                ! $fillsFinance => null,
                $isEnterprise => (int) round(300_000 + 1_200_000 * $chance('other'), -4),
                $chance('other') < 0.15 => (int) round(100_000 + 400_000 * $chance('other2'), -4),
                $tier === 'telat' && $chance('other') < 0.5 => null,
                default => 0,
            },
            'costs' => ! $fillsFinance || $chance('costs') < 0.1 ? null : (int) round($sales * (0.55 + 0.2 * $chance('margin')), -3),
        ], $items];
    }

    private function seedIssues(MonthlyReport $report, int $n, int $i): void
    {
        if ($this->chance($n, $i, 'problem') < 0.28) {
            [$category, $description] = DemoData::PROBLEMS[($n * 3 + $i) % count(DemoData::PROBLEMS)];
            $report->issues()->create(['kind' => IssueKind::Problem, 'category' => $category, 'description' => $description]);
        }

        if ($this->chance($n, $i, 'need') < 0.22) {
            [$category, $description] = DemoData::NEEDS[($n + $i) % count(DemoData::NEEDS)];
            $report->issues()->create(['kind' => IssueKind::Support, 'category' => $category, 'description' => $description]);
        }
    }

    /**
     * Tindak lanjut beragam: isian lama kebanyakan sudah Selesai, isian baru sebagian masih Baru (ada yang lebih
     * dari 30 hari, muncul di "Kebutuhan belum ditindaklanjuti").
     */
    private function seedFollowUps(MonthlyReport $report, int $n, int $i, User $verifier, Carbon $submittedAt): void
    {
        $paths = [
            [],
            ['reviewed'],
            ['reviewed', 'planned'],
            ['reviewed', 'planned', 'in_progress'],
            ['reviewed', 'in_progress', 'done'],
            ['reviewed', 'unable'],
        ];

        foreach ($report->issues as $index => $issue) {
            $r = $this->chance($n, $i, "follow{$index}");
            $path = $paths[match (true) {
                // Isian sampai Juni 2026 sudah lama ditangani.
                $i <= 8 => $r < 0.8 ? 4 : ($r < 0.9 ? 5 : 3),
                $r < 0.35 => 0,
                $r < 0.5 => 1,
                $r < 0.62 => 2,
                $r < 0.75 => 3,
                $r < 0.92 => 4,
                default => 5,
            }];

            $from = FollowUpStatus::New;
            $at = $submittedAt->copy()->addDays(3);
            $last = null;
            foreach ($path as $to) {
                if ($at->gte($this->now)) {
                    break;
                }

                IssueFollowUp::forceCreate([
                    'report_issue_id' => $issue->id,
                    'user_id' => $verifier->id,
                    'opd_id' => $verifier->opd_id,
                    'from_status' => $from,
                    'to_status' => FollowUpStatus::from($to),
                    'note' => DemoData::FOLLOW_UP_NOTES[$to],
                    'acted_on' => $at->toDateString(),
                    'created_at' => $at,
                ]);
                $from = FollowUpStatus::from($to);
                $last = $at->copy();
                $at->addDays(5 + $n % 6);
            }

            if ($last) {
                $issue->forceFill(['follow_up_status' => $from, 'follow_up_opd_id' => $verifier->opd_id, 'follow_up_at' => $last])->save();
            }
        }
    }

    /** kg/liter boleh satu desimal; satuan hitung (ekor, bungkus, buah, ...) selalu bulat. */
    private function quantity(float $value, string $unit): float|int
    {
        if (in_array($unit, ['kg', 'liter'])) {
            return $value >= 100 ? max(1, (int) round($value)) : max(0.5, round($value, 1));
        }

        return max(1, (int) round($value));
    }

    /** Angka 0–1 yang tetap untuk kombinasi kelompok, bulan, dan kunci yang sama. */
    private function chance(int $n, int $i, string $key): float
    {
        return hexdec(substr(md5("{$n}|{$i}|{$key}"), 0, 8)) / 0xFFFFFFFF;
    }
}
