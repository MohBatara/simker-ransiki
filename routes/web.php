<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Kelompok;
use App\Http\Controllers\Opd;
use App\Http\Controllers\Publik;
use App\Http\Controllers\Shared;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', Publik\HomeController::class)->name('home');
Route::get('/profil', [Publik\PageController::class, 'profile'])->name('public.profile');
Route::get('/kontak', [Publik\PageController::class, 'contact'])->name('public.contact');
Route::get('/direktori', [Publik\DirectoryController::class, 'index'])->name('public.directory');
Route::get('/direktori/{slug}', [Publik\DirectoryController::class, 'show'])->name('public.organization');
Route::get('/produk', Publik\ProductController::class)->name('public.products');
Route::get('/berita', [Publik\PostController::class, 'index'])->name('public.posts');
Route::get('/berita/{slug}', [Publik\PostController::class, 'show'])->name('public.post');
Route::get('/foto-berita/{post}', [Publik\PostController::class, 'photo'])->name('public.post-photo')->whereNumber('post');
Route::get('/foto-{slot}', [Publik\SiteImageController::class, 'slot'])->name('public.site-photo')->whereIn('slot', ['sampul', 'tentang']);
Route::get('/foto-bidang/{sector}', [Publik\SiteImageController::class, 'sector'])->name('public.sector-photo')->whereNumber('sector');
Route::get('/sitemap.xml', [Publik\SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [Publik\SitemapController::class, 'robots'])->name('robots');

// Foto produk publik boleh dilihat tanpa login; controller memeriksa hak untuk foto yang tidak publik.
Route::get('/foto-produk/{product}', [Shared\ProductPhotoController::class, 'show'])->name('product-photos.show')->whereNumber('product');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/lupa-kata-sandi', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/lupa-kata-sandi', [PasswordResetController::class, 'store'])->name('password.email')->middleware('throttle:password-reset');
});

// Tautan dari email (atur ulang kata sandi, undangan) tidak memakai "guest": bisa dibuka di browser yang sedang
// masuk dengan akun lain (mis. Admin yang menguji). Akun itu dikeluarkan saat kata sandi baru disimpan.
Route::get('/atur-ulang-kata-sandi/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::post('/atur-ulang-kata-sandi', [PasswordResetController::class, 'update'])->name('password.update')->middleware('throttle:10,1');

// Undangan akun (tautan bertanda tangan, sekali pakai). Tanda tangan dicek di controller agar tautan
// kedaluwarsa mendapat halaman penjelasan, bukan 403.
Route::controller(InvitationController::class)->prefix('undangan/{user}/{token}')->whereNumber('user')->group(function () {
    Route::get('/', 'show')->name('invitation.show');
    Route::post('/', 'store')->name('invitation.store')->middleware('throttle:10,1');
});

/**
 * Daftar, detail (dengan tab), dan ubah kelompok untuk Admin dan OPD, plus CRUD produk dan izin.
 * Didaftarkan di dalam grup area sehingga namanya admin.organizations.* dan opd.organizations.*.
 */
$organizationRoutes = function () {
    Route::controller(Shared\OrganizationController::class)->prefix('kelompok')->name('organizations.')->group(function () {
        Route::get('/', 'index')->name('index')->can('viewAny', Organization::class);
        Route::get('/unduh', 'export')->name('export')->can('viewAny', Organization::class);

        Route::whereNumber('organization')->group(function () {
            Route::get('/{organization}', 'show')->name('show')->can('view', 'organization');
            Route::get('/{organization}/ringkas', 'summary')->name('summary')->can('view', 'organization');
            Route::get('/{organization}/produk', 'products')->name('products')->can('view', 'organization');
            Route::get('/{organization}/izin', 'licenses')->name('licenses')->can('view', 'organization');
            Route::get('/{organization}/izin/{license}', 'license')->name('licenses.show')->scopeBindings()->can('view', 'license');
            Route::get('/{organization}/laporan', 'reports')->name('reports')->can('view', 'organization');
            Route::get('/{organization}/riwayat', 'history')->name('history')->can('viewHistory', 'organization');
            Route::post('/{organization}/verifikasi-profil', 'verifyProfile')->name('verify-profile')->can('verifyProfile', 'organization');
            Route::get('/{organization}/ubah', 'edit')->name('edit')->can('update', 'organization');
            Route::put('/{organization}', 'update')->name('update')->can('update', 'organization');
        });
    });

    // Antrean "Profil menunggu verifikasi": kelompok yang terlihat pengguna (visibleTo) dengan profil berubah.
    Route::get('/verifikasi-profil', [Shared\ProfileVerificationController::class, 'index'])->name('profile-verifications.index')
        ->can('viewAny', Organization::class);

    // scopeBindings: produk/izin harus milik kelompok di URL, jika tidak 404. Hak dicek di Form Request/Policy.
    Route::prefix('kelompok/{organization}')->name('organizations.')->whereNumber('organization')->scopeBindings()->group(function () {
        Route::post('/produk', [Shared\ProductController::class, 'store'])->name('products.store');
        Route::put('/produk/{product}', [Shared\ProductController::class, 'update'])->name('products.update');
        Route::delete('/produk/{product}', [Shared\ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/izin', [Shared\LicenseController::class, 'store'])->name('licenses.store');
        Route::put('/izin/{license}', [Shared\LicenseController::class, 'update'])->name('licenses.update');
        Route::delete('/izin/{license}', [Shared\LicenseController::class, 'destroy'])->name('licenses.destroy');
    });
};

/**
 * Laporan untuk peninjau (Admin dan OPD). Hak tindakan dicek ulang di App\Actions\Reports\ReviewReport
 * setelah baris dikunci, agar peninjau kedua mendapat pesan "sudah ditinjau", bukan 403.
 */
$reportRoutes = function () {
    Route::controller(Shared\MonthlyReportController::class)->prefix('laporan')->name('reports.')->group(function () {
        Route::get('/', 'index')->name('index');

        Route::whereNumber('report')->group(function () {
            Route::get('/{report}', 'show')->name('show')->can('view', 'report');
            Route::get('/{report}/pdf', [Shared\ReportExportController::class, 'report'])->name('pdf')->can('view', 'report');
            Route::post('/{report}/verifikasi', 'verify')->name('verify');
            Route::post('/{report}/minta-revisi', 'requestRevision')->name('revision');
        });
    });

    // Isi laporan atas nama kelompok (OPD pembina dan Admin). Hak dicek MonthlyReportPolicy.
    Route::controller(Shared\ReportEntryController::class)->name('reports.')->group(function () {
        Route::post('/kelompok/{organization}/laporan', 'store')->name('store')->whereNumber('organization');
        Route::whereNumber('report')->prefix('laporan')->group(function () {
            Route::get('/{report}/ubah', 'edit')->name('edit')->can('update', 'report');
            Route::patch('/{report}/draf', 'saveDraft')->name('draft')->can('update', 'report')->middleware('throttle:60,1');
            Route::post('/{report}/ajukan', 'submit')->name('submit')->can('submit', 'report');
        });
    });

    // Rekap per periode; isinya dibatasi kelompok yang terlihat pengguna (visibleTo).
    Route::get('/laporan/rekap', [Shared\ReportExportController::class, 'recap'])->name('reports.recap');
    // Masalah & kebutuhan + tindak lanjutnya; isi dibatasi ReportIssue::reviewableBy, hak per baris ReportIssuePolicy.
    Route::controller(Shared\ReportIssueController::class)->prefix('masalah')->name('issues.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/unduh', 'export')->name('export');
        Route::get('/{issue}', 'show')->name('show')->whereNumber('issue')->can('view', 'issue');
        Route::post('/{issue}/tindak-lanjut', 'followUp')->name('follow-up')->whereNumber('issue')->can('followUp', 'issue');
    });
    Route::get('/cari', [Shared\SearchController::class, 'index'])->name('search');

    // Statistik ekonomi dan legalitas; angka dibatasi kelompok yang terlihat pengguna (visibleTo).
    Route::controller(Shared\StatisticsController::class)->prefix('statistik')->name('statistics.')->group(function () {
        Route::get('/', 'economy')->name('economy');
        Route::get('/legalitas', 'legal')->name('legal');
        Route::get('/legalitas/unduh', 'legalExport')->name('legal.export');
    });
};

Route::middleware('auth')->group(function () use ($organizationRoutes, $reportRoutes) {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('active')->group(function () use ($organizationRoutes, $reportRoutes) {
        Route::get('/akun/kata-sandi', [PasswordController::class, 'edit'])->name('account.password.edit');
        Route::put('/akun/kata-sandi', [PasswordController::class, 'update'])->name('account.password.update');

        Route::middleware('password.changed')->group(function () use ($organizationRoutes, $reportRoutes) {
            Route::controller(Shared\NotificationController::class)->prefix('notifikasi')->name('notifications.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/tandai-semua', 'markAllRead')->name('read-all');
                Route::get('/{notification}', 'open')->name('open')->whereUuid('notification');
            });

            // File dipakai bersama semua peran; hak ditentukan Policy atas induknya.
            Route::controller(Shared\AttachmentController::class)->prefix('lampiran')->name('attachments.')->group(function () {
                Route::post('/{type}/{id}', 'store')->name('store')->whereIn('type', ['kelompok', 'izin', 'laporan'])->whereNumber('id')->middleware('throttle:uploads');
                Route::get('/{attachment}', 'show')->name('show')->whereNumber('attachment')->can('view', 'attachment');
                Route::get('/{attachment}/unduh', 'download')->name('download')->whereNumber('attachment')->can('view', 'attachment');
                Route::delete('/{attachment}', 'destroy')->name('destroy')->whereNumber('attachment')->can('delete', 'attachment');
            });
            Route::post('/foto-produk/{product}', [Shared\ProductPhotoController::class, 'store'])->name('product-photos.store')->whereNumber('product')->middleware('throttle:uploads');
            Route::delete('/foto-produk/{product}', [Shared\ProductPhotoController::class, 'destroy'])->name('product-photos.destroy')->whereNumber('product')->can('update', 'product');

            Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () use ($organizationRoutes, $reportRoutes) {
                Route::get('/', Admin\DashboardController::class)->name('dashboard');

                Route::controller(Admin\OrganizationController::class)->prefix('kelompok')->name('organizations.')->group(function () {
                    Route::get('/tambah', 'create')->name('create')->can('create', Organization::class);
                    Route::post('/', 'store')->name('store')->can('create', Organization::class);
                    Route::patch('/{organization}/status', 'updateStatus')->name('status')->whereNumber('organization')->can('administer', 'organization');
                    Route::delete('/{organization}', 'destroy')->name('destroy')->whereNumber('organization')->can('delete', 'organization');
                });
                // Impor kelompok dari spreadsheet: unggah → pratinjau → impor → hasil. Token = UUID acak milik pengunggah.
                Route::controller(Admin\OrganizationImportController::class)->prefix('kelompok/impor')->name('organizations.import.')
                    ->can('create', Organization::class)->group(function () {
                        Route::get('/', 'create')->name('create');
                        Route::get('/template', 'template')->name('template');
                        Route::post('/', 'store')->name('store')->middleware('throttle:uploads');
                        Route::whereUuid('token')->group(function () {
                            Route::get('/{token}', 'preview')->name('preview');
                            Route::post('/{token}', 'import')->name('run');
                            Route::get('/{token}/hasil', 'result')->name('result');
                            Route::get('/{token}/gagal', 'failed')->name('failed');
                        });
                    });
                // Kemungkinan data ganda: daftar pasangan nama mirip + "Bukan data ganda". Tanpa gabung otomatis.
                Route::controller(Admin\DuplicateController::class)->prefix('kelompok/data-ganda')->name('organizations.duplicates.')
                    ->can('create', Organization::class)->group(function () {
                        Route::get('/', 'index')->name('index');
                        Route::post('/bukan-ganda', 'dismiss')->name('dismiss');
                    });
                Route::get('/kelompok/{organization}/akun', [Shared\OrganizationController::class, 'users'])
                    ->name('organizations.users')->whereNumber('organization')->can('view', 'organization');
                $organizationRoutes();
                $reportRoutes();
                Route::post('/laporan/{report}/buka-kembali', [Shared\MonthlyReportController::class, 'reopen'])
                    ->name('reports.reopen')->whereNumber('report');
                // Antrean Admin: laporan yang tidak bisa diverifikasi petugas OPD (tanpa OPD pembina / pemisahan tugas).
                Route::get('/verifikasi', [Shared\MonthlyReportController::class, 'queue'])->name('verifications.index');

                Route::controller(Admin\UserController::class)->prefix('pengguna')->name('users.')->group(function () {
                    Route::get('/', 'index')->name('index')->can('viewAny', User::class);
                    Route::get('/tambah', 'create')->name('create')->can('create', User::class);
                    Route::post('/', 'store')->name('store')->can('create', User::class);

                    Route::whereNumber('user')->group(function () {
                        Route::get('/{user}', 'show')->name('show')->can('view', 'user');
                        Route::get('/{user}/ubah', 'edit')->name('edit')->can('update', 'user');
                        Route::put('/{user}', 'update')->name('update')->can('update', 'user');
                        Route::post('/{user}/reset-kata-sandi', 'resetPassword')->name('reset-password')->can('resetPassword', 'user');
                        Route::post('/{user}/undangan', 'resendInvitation')->name('invitation')->can('invite', 'user');
                        Route::patch('/{user}/aktifkan', 'activate')->name('activate')->can('activate', 'user');
                        Route::patch('/{user}/nonaktifkan', 'deactivate')->name('deactivate')->can('deactivate', 'user');
                    });
                });

                Route::controller(Admin\SiteContentController::class)->prefix('konten')->name('content.')->group(function () {
                    Route::get('/', 'edit')->name('edit');
                    Route::put('/', 'update')->name('update');
                    Route::post('/foto-{slot}', 'storePhoto')->name('photo.store')->whereIn('slot', ['sampul', 'tentang'])->middleware('throttle:uploads');
                    Route::delete('/foto-{slot}', 'destroyPhoto')->name('photo.destroy')->whereIn('slot', ['sampul', 'tentang']);
                    Route::post('/foto-bidang/{sector}', 'storeSectorPhoto')->name('sector-photo.store')->whereNumber('sector')->middleware('throttle:uploads');
                    Route::delete('/foto-bidang/{sector}', 'destroySectorPhoto')->name('sector-photo.destroy')->whereNumber('sector');
                });

                Route::controller(Admin\PostController::class)->prefix('berita')->name('posts.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/tambah', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::post('/pratinjau', 'preview')->name('preview')->middleware('throttle:60,1');
                    Route::whereNumber('post')->group(function () {
                        Route::get('/{post}/ubah', 'edit')->name('edit');
                        Route::put('/{post}', 'update')->name('update');
                        Route::delete('/{post}', 'destroy')->name('destroy');
                        Route::post('/{post}/foto', 'storeCover')->name('cover.store')->middleware('throttle:uploads');
                        Route::delete('/{post}/foto', 'destroyCover')->name('cover.destroy');
                    });
                });

                Route::controller(Admin\MasterDataController::class)->prefix('data-master')->name('master.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{type}', 'items')->name('items');
                    Route::post('/{type}', 'store')->name('store');
                    Route::put('/{type}/{id}', 'update')->name('update')->whereNumber('id');
                    Route::delete('/{type}/{id}', 'destroy')->name('destroy')->whereNumber('id');
                });
            });

            Route::prefix('opd')->name('opd.')->middleware('role:opd')->group(function () use ($organizationRoutes, $reportRoutes) {
                Route::get('/', Opd\DashboardController::class)->name('dashboard');
                $organizationRoutes();
                $reportRoutes();
                Route::get('/verifikasi', [Shared\MonthlyReportController::class, 'queue'])->name('verifications.index');
            });

            Route::prefix('kelompok')->name('kelompok.')->middleware('role:kelompok')->group(function () {
                Route::get('/', Kelompok\DashboardController::class)->name('dashboard');

                Route::controller(Kelompok\ProfileController::class)->group(function () {
                    Route::get('/profil', 'show')->name('profile.show');
                    Route::get('/profil/ubah', 'edit')->name('profile.edit');
                    Route::put('/profil', 'update')->name('profile.update');
                    Route::get('/produk', 'products')->name('products.index');
                    Route::get('/izin', 'licenses')->name('licenses.index');
                    Route::get('/izin/{license}', 'license')->name('licenses.show')->whereNumber('license')->can('view', 'license');
                    Route::patch('/legalitas', 'updateLegal')->name('legal.update');
                });

                Route::post('/produk', [Shared\ProductController::class, 'store'])->name('products.store');
                Route::put('/produk/{product}', [Shared\ProductController::class, 'update'])->name('products.update')->whereNumber('product');
                Route::delete('/produk/{product}', [Shared\ProductController::class, 'destroy'])->name('products.destroy')->whereNumber('product');
                Route::post('/izin', [Shared\LicenseController::class, 'store'])->name('licenses.store');
                Route::put('/izin/{license}', [Shared\LicenseController::class, 'update'])->name('licenses.update')->whereNumber('license');
                Route::delete('/izin/{license}', [Shared\LicenseController::class, 'destroy'])->name('licenses.destroy')->whereNumber('license');

                Route::controller(Kelompok\MonthlyReportController::class)->prefix('laporan')->name('reports.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');

                    Route::whereNumber('report')->group(function () {
                        Route::get('/{report}', 'show')->name('show')->can('view', 'report');
                        Route::get('/{report}/pdf', [Shared\ReportExportController::class, 'report'])->name('pdf')->can('view', 'report');
                        Route::get('/{report}/ubah', 'edit')->name('edit')->can('update', 'report');
                        Route::patch('/{report}/draf', 'saveDraft')->name('draft')->can('update', 'report')->middleware('throttle:60,1');
                        Route::post('/{report}/ajukan', 'submit')->name('submit')->can('submit', 'report');
                    });
                });
            });
        });
    });
});
