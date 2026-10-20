<?php

namespace Database\Seeders;

use App\Actions\Files\StoreAttachment;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Data demo untuk pengembangan dan pemolesan tampilan (CLAUDE.md Bagian 10). Isinya di DemoData; semua
 * nama, nomor, dan email fiktif. Tidak memakai angka acak: setiap variasi diturunkan dari crc32 nomor
 * kelompok + bulan, sehingga migrate:fresh --seed selalu menghasilkan data yang sama.
 */
class DemoSeeder extends Seeder
{
    /** Bulan laporan pertama; 6 bulan sampai bulan wajib lapor (September 2026). */
    private const FIRST_PERIOD = '2026-04-01';

    /** 6 bulan riwayat + bulan berjalan (Oktober 2026, hanya draf kelompok 1). */
    private const PERIOD_COUNT = 7;

    /** Indeks bulan wajib lapor (September 2026) dalam rentang di atas. */
    private const DUE_INDEX = 5;

    /**
     * Masalah & kebutuhan demo (sengaja sedikit): [kelompok, indeks bulan, jenis, kategori, uraian, jalur tindak lanjut].
     * Kebutuhan kelompok 7 bulan Agustus dibiarkan Baru lebih dari 30 hari (tugas "kebutuhan belum ditindaklanjuti").
     */
    private const ISSUES = [
        [6, 5, IssueKind::Problem, 'bahan_baku', 'Pasokan ikan tongkol berkurang karena angin kencang, produksi abon turun.', []],
        [7, 4, IssueKind::Support, 'pelatihan', 'Pelatihan vaksinasi dan perawatan ayam untuk anggota baru.', []],
        [2, 4, IssueKind::Support, 'pemasaran', 'Bantuan kemasan dan label agar keripik bisa masuk toko oleh-oleh di kota.', ['reviewed', 'planned']],
        [11, 3, IssueKind::Problem, 'peralatan', 'Mesin sangrai kopi sering mati saat dipakai lama.', ['reviewed', 'in_progress', 'done']],
    ];

    public const TEMPORARY_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

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
            // Profil Ransiki: teks contoh. Luas wilayah, jumlah penduduk, dan ibu kota distrik sengaja kosong sampai ada data resmi.
            'profile_intro' => 'Ransiki adalah distrik pesisir tempat kebun kakao, laut, dan usaha olahan rumahan menjadi sumber penghidupan utama warganya.',
            'profile_regency' => 'Manokwari Selatan',
            'profile_province' => 'Papua Barat',
            'profile_data_source' => 'Data contoh untuk uji tampilan, belum data resmi',
            'office_address' => 'Kantor Distrik Ransiki, Kabupaten Manokwari Selatan',
            'office_phone' => '0812-0000-0002',
            'office_whatsapp' => '0812-0000-0003',
            'office_email' => 'distrik@example.test',
            'office_hours' => 'Senin sampai Jumat, pukul 08.00 sampai 15.00 WIT',
        ]);

        $this->call(OfficialPhotoSeeder::class);

        if ($this->photosEnabled()) {
            $this->seedSitePhotos();
        }

        $this->seedProfileVerification($organizations, $opdUsers);
        $this->seedPosts($admin, $organizations);

        foreach ($organizations as $n => $seeded) {
            $this->seedReports($n, DemoData::ORGANIZATIONS[$n], $seeded, $opdUsers, $groupUsers[$n] ?? null);
        }

        // Angka publik (beranda, direktori) di-cache 10 menit; dikosongkan agar langsung sesuai data demo baru.
        Cache::flush();
    }

    /**
     * Foto hanya diproses di local: tes tidak menulis ke storage asli dan tetap cepat. Sumbernya foto asli klien di
     * database/seeders/media (produk, kelompok, berita) dan foto sementara di database/seeders/photos (sampul, Tentang,
     * bidang usaha, jenis kelembagaan); path tetap (…-demo.jpg) agar seed ulang menimpa file yang sama.
     */
    private function photosEnabled(): bool
    {
        return app()->isLocal() && is_dir(database_path('seeders/photos'));
    }

    /** "media/…" = foto asli klien (database/seeders/media); nama file saja = foto sementara di database/seeders/photos. */
    private function photo(string $file): string
    {
        return database_path('seeders/'.(str_starts_with($file, 'media/') ? $file : 'photos/'.$file));
    }

    /**
     * Foto sementara untuk presentasi (database/seeders/media/sementara/{kelompok|produk|berita}/{slug}.jpg|jpeg|png|webp,
     * tidak masuk git; daftar di DAFTAR-FOTO.md). Dikecilkan otomatis saat disimpan (maks 1600 px).
     */
    private function temporaryPhoto(string $folder, string $slug): ?string
    {
        foreach (self::TEMPORARY_EXTENSIONS as $extension) {
            $path = database_path("seeders/media/sementara/{$folder}/{$slug}.{$extension}");
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** Foto sementara bila ada, selain itu foto klien yang cocok (bila ada). */
    private function photoFor(string $folder, string $slug, ?string $fallback): ?string
    {
        return $this->temporaryPhoto($folder, $slug) ?? ($fallback ? $this->photo($fallback) : null);
    }

    private function storeWidePhoto(string $file, string $path, array $budgets = [], array $widths = ImageVariants::WIDE): string
    {
        ImageVariants::store('local', $this->photo($file), $path, $widths, $budgets);

        return $path;
    }

    private function storeProductPhoto(Product $product, string $source): void
    {
        $base = sprintf('products/%d/demo', $product->id);

        ImageVariants::store(Product::PHOTO_DISK, $source, $base.'.jpg', ImageVariants::PRODUCT);
        $thumbnail = ImageProcessor::process($source, 'image/jpeg', ImageProcessor::THUMBNAIL_SIDE, 'jpeg');
        Storage::disk(Product::PHOTO_DISK)->put($base.Product::THUMBNAIL_SUFFIX.'.jpg', $thumbnail['contents']);

        $product->forceFill(['photo_path' => $base.'.jpg'])->save();
    }

    /**
     * Foto kelompok (belum ada kolom foto profil kelompok): lampiran gambar kelompok lewat StoreAttachment yang sama dengan
     * unggahan, sehingga tampil di kartu Foto detail kelompok dan hanya bisa dibuka lewat route lampiran (Policy).
     */
    private function storeOrganizationPhotos(Organization $organization, array $files, User $uploader): void
    {
        $captions = OfficialPhotoSeeder::captions();

        foreach ($files as $file) {
            $path = str_starts_with($file, '/') ? $file : $this->photo($file);
            $name = ($captions[substr($file, strlen('media/'))] ?? 'Foto kelompok '.$organization->name).'.jpg';
            app(StoreAttachment::class)->handle($organization, new UploadedFile($path, $name, null, null, true), $uploader);
        }
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

        // Foto jenis kelembagaan untuk tiga jenis saja, agar kartu berfoto dan tanpa foto bisa dibandingkan.
        foreach (DemoData::INSTITUTION_TYPE_PHOTOS as $code => $file) {
            $type = InstitutionType::where('code', $code)->firstOrFail();
            $type->forceFill(['photo_path' => $this->storeWidePhoto($file, "site/jenis-{$type->id}-demo.jpg", widths: ImageVariants::HERO)])->save();
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

    /**
     * Empat berita terbit (tiga berfoto asli klien; satu kegiatan dengan tanggal kegiatan ke depan untuk Agenda). Foto
     * sementara di media/sementara/berita/{slug} didahulukan. Elemen ke-9 opsional = tanggal kegiatan.
     */
    private function seedPosts(User $admin, array $organizations): void
    {
        $posts = [
            [
                'Pelatihan pengasapan ikan untuk kelompok nelayan', 'kegiatan', '2026-10-05 09:00', 'published', [5, 6], 'media/berita/pendampingan-nelayan.jpg',
                'Pelatihan pengasapan ikan yang lebih bersih untuk kelompok nelayan dan pengolah ikan, terbuka bagi anggota yang mendaftar.',
                "Pelatihan ini membahas cara mengasap ikan dengan tungku tertutup agar asap tidak menempel berlebihan dan ikan lebih tahan lama.\n\nPendaftaran lewat petugas OPD pembina paling lambat satu pekan sebelum pelatihan.",
                '2026-11-12',
            ],
            [
                'Batas pengiriman laporan bulanan setiap tanggal 10', 'berita', '2026-09-01 08:00', 'published', [], null,
                'Kelompok diminta mengirim laporan bulanan paling lambat tanggal 10 bulan berikutnya pukul 23.59 WIT.',
                "Laporan bulanan berisi kegiatan, produksi dan penjualan, keuangan, masalah, dan kebutuhan dukungan.\n\nKelompok yang belum punya akun dapat melapor lewat petugas OPD pembina. Bila tidak ada kegiatan, pilih \"Tidak ada aktivitas bulan ini\".",
            ],
            [
                'Produk olahan kelompok dijual bersama di Pasar Ransiki', 'kegiatan', '2026-08-19 10:00', 'published', [2, 4, 12], 'media/berita/pasar-ransiki.jpg',
                'Keripik pisang, noken, dan sagu lempeng dari tiga kelompok binaan dijual di lapak bersama Pasar Ransiki.',
                "Tiga kelompok usaha binaan berjualan di lapak bersama Pasar Ransiki selama tiga hari pasar.\n\nSebagian besar produk habis terjual pada hari kedua. Beberapa pembeli meminta kontak kelompok untuk pesanan rutin.\n\nPengalaman ini menunjukkan kemasan dan label yang rapi sangat membantu penjualan.",
            ],
            [
                'Petugas meninjau kandang kelompok peternak ayam kampung', 'kegiatan', '2026-07-08 08:00', 'published', [7], 'media/berita/kandang-ayam.jpg',
                'Petugas penyuluh mengunjungi kandang Kelompok Peternak Ayam Kampung Subur Makmur untuk memeriksa kebersihan kandang dan kesehatan ayam.',
                "Kunjungan dilakukan pagi hari saat ayam diberi pakan. Petugas memeriksa ventilasi kandang, tempat minum, dan catatan vaksinasi.\n\nAnggota diminta memisahkan ayam yang sakit dan mencatat jumlah ayam setiap pekan agar laporan bulanan lebih mudah diisi.",
            ],
        ];

        foreach ($posts as $entry) {
            [$title, $category, $publishedAt, $status, $related, $photo, $summary, $body] = $entry;
            $post = new Post([
                'title' => $title,
                'slug' => Post::uniqueSlug($title),
                'category' => $category,
                'summary' => $summary,
                'body' => $body,
                'status' => $status,
                'published_at' => $publishedAt ? Carbon::parse($publishedAt) : null,
                'event_date' => $entry[8] ?? null,
            ]);
            $post->forceFill(['created_by' => $admin->id, 'updated_by' => $admin->id])->save();
            $post->organizations()->sync(collect($related)->map(fn (int $n) => $organizations[$n]['organization']->id)->all());

            if ($this->photosEnabled() && $source = $this->photoFor('berita', $post->slug, $photo)) {
                $path = "posts/berita-{$post->id}-demo.jpg";
                ImageVariants::store(Post::PHOTO_DISK, $source, $path, ImageVariants::WIDE, ImageVariants::HERO_BUDGET);
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
            'description' => $n % 11 === 0 ? null : strtr(DemoData::SECTOR_TEXT[$data['text'] ?? $primarySector][0], ['{produk}' => $productList, '{kampung}' => $village->name]),
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
                'is_public' => $organization->is_public,
                'is_featured' => $organization->is_public && $index === 0 && ($photo !== null || $n % 4 === 2 || ($data['featured'] ?? false)),
            ]);

            if ($this->photosEnabled() && $source = $this->photoFor('produk', $product->slug, $photo)) {
                $this->storeProductPhoto($product, $source);
            }

            $products[] = [$product, $base, $price, $season];
        }

        if ($this->photosEnabled()) {
            $temporary = $this->temporaryPhoto('kelompok', $organization->slug);
            $this->storeOrganizationPhotos($organization, $temporary ? [$temporary] : ($data['photos'] ?? []), $admin);
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

        foreach (range(0, self::PERIOD_COUNT - 1) as $i) {
            $period = Carbon::parse(self::FIRST_PERIOD)->addMonthsNoOverflow($i);
            if ($organization->reporting_starts_on->gt($period)) {
                continue;
            }

            $status = $this->reportStatus($n, $data, $i);
            if ($status === null) {
                continue;
            }

            [$status, $steps] = $this->timeline($status, $n, $tier, $i, $period, $verifier, $groupUser);

            // Kelompok nelayan tidak melaut sebulan karena cuaca buruk (contoh "Tidak ada aktivitas bulan ini").
            $noActivity = $n === 5 && $i === 1;

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

            $this->saveHistory($report, $steps);

            if (in_array($status, [ReportStatus::Submitted, ReportStatus::Verified])) {
                $this->seedIssues($report, $n, $i, $verifier, $steps[0][1]);
            }
        }
    }

    /**
     * Status laporan: April sampai Agustus 2026 terverifikasi semua (grafik terisi), September (bulan wajib lapor, tenggat
     * 10 Oktober) mengikuti kunci `september` kelompok, Oktober (bulan berjalan) hanya draf kelompok 1.
     */
    private function reportStatus(int $n, array $data, int $i): ?ReportStatus
    {
        return match (true) {
            $i < self::DUE_INDEX => ReportStatus::Verified,
            $i === self::DUE_INDEX => ReportStatus::from($data['september'] ?? 'verified'),
            default => $n === 1 ? ReportStatus::Draft : null,
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
        $text = DemoData::SECTOR_TEXT[$data['text'] ?? $data['sectors'][0]];
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

    /** Isian E/F dari ISSUES beserta riwayat tindak lanjutnya (mulai 3 hari setelah diajukan, jarak 5 hari). */
    private function seedIssues(MonthlyReport $report, int $n, int $i, User $verifier, Carbon $submittedAt): void
    {
        foreach (self::ISSUES as [$group, $index, $kind, $category, $description, $path]) {
            if ($group !== $n || $index !== $i) {
                continue;
            }

            $issue = $report->issues()->create(['kind' => $kind, 'category' => $category, 'description' => $description]);
            $from = FollowUpStatus::New;
            $at = $submittedAt->copy()->addDays(3);
            $last = null;

            foreach ($path as $to) {
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
                $at->addDays(5);
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
