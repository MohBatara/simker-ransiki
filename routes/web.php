<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
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
Route::get('/foto-sampul', Publik\CoverPhotoController::class)->name('public.cover');
Route::get('/sitemap.xml', [Publik\SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [Publik\SitemapController::class, 'robots'])->name('robots');

// Foto produk publik boleh dilihat tanpa login; controller memeriksa hak untuk foto yang tidak publik.
Route::get('/foto-produk/{product}', [Shared\ProductPhotoController::class, 'show'])->name('product-photos.show')->whereNumber('product');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

/**
 * Daftar, detail (dengan tab), dan ubah kelompok untuk Admin dan OPD, plus CRUD produk dan izin.
 * Didaftarkan di dalam grup area sehingga namanya admin.organizations.* dan opd.organizations.*.
 */
$organizationRoutes = function () {
    Route::controller(Shared\OrganizationController::class)->prefix('kelompok')->name('organizations.')->group(function () {
        Route::get('/', 'index')->name('index')->can('viewAny', Organization::class);

        Route::whereNumber('organization')->group(function () {
            Route::get('/{organization}', 'show')->name('show')->can('view', 'organization');
            Route::get('/{organization}/produk', 'products')->name('products')->can('view', 'organization');
            Route::get('/{organization}/izin', 'licenses')->name('licenses')->can('view', 'organization');
            Route::get('/{organization}/izin/{license}', 'license')->name('licenses.show')->scopeBindings()->can('view', 'license');
            Route::get('/{organization}/laporan', 'reports')->name('reports')->can('view', 'organization');
            Route::get('/{organization}/ubah', 'edit')->name('edit')->can('update', 'organization');
            Route::put('/{organization}', 'update')->name('update')->can('update', 'organization');
        });
    });

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

Route::middleware('auth')->group(function () use ($organizationRoutes) {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('active')->group(function () use ($organizationRoutes) {
        Route::get('/akun/kata-sandi', [PasswordController::class, 'edit'])->name('account.password.edit');
        Route::put('/akun/kata-sandi', [PasswordController::class, 'update'])->name('account.password.update');

        Route::middleware('password.changed')->group(function () use ($organizationRoutes) {
            // File dipakai bersama semua peran; hak ditentukan Policy atas induknya.
            Route::controller(Shared\AttachmentController::class)->prefix('lampiran')->name('attachments.')->group(function () {
                Route::post('/{type}/{id}', 'store')->name('store')->whereIn('type', ['kelompok', 'izin'])->whereNumber('id')->middleware('throttle:uploads');
                Route::get('/{attachment}', 'show')->name('show')->whereNumber('attachment')->can('view', 'attachment');
                Route::get('/{attachment}/unduh', 'download')->name('download')->whereNumber('attachment')->can('view', 'attachment');
                Route::delete('/{attachment}', 'destroy')->name('destroy')->whereNumber('attachment')->can('delete', 'attachment');
            });
            Route::post('/foto-produk/{product}', [Shared\ProductPhotoController::class, 'store'])->name('product-photos.store')->whereNumber('product')->middleware('throttle:uploads');
            Route::delete('/foto-produk/{product}', [Shared\ProductPhotoController::class, 'destroy'])->name('product-photos.destroy')->whereNumber('product')->can('update', 'product');

            Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () use ($organizationRoutes) {
                Route::get('/', Admin\DashboardController::class)->name('dashboard');

                Route::controller(Admin\OrganizationController::class)->prefix('kelompok')->name('organizations.')->group(function () {
                    Route::get('/tambah', 'create')->name('create')->can('create', Organization::class);
                    Route::post('/', 'store')->name('store')->can('create', Organization::class);
                    Route::patch('/{organization}/status', 'updateStatus')->name('status')->whereNumber('organization')->can('administer', 'organization');
                    Route::delete('/{organization}', 'destroy')->name('destroy')->whereNumber('organization')->can('delete', 'organization');
                });
                Route::get('/kelompok/{organization}/akun', [Shared\OrganizationController::class, 'users'])
                    ->name('organizations.users')->whereNumber('organization')->can('view', 'organization');
                $organizationRoutes();

                Route::controller(Admin\UserController::class)->prefix('pengguna')->name('users.')->group(function () {
                    Route::get('/', 'index')->name('index')->can('viewAny', User::class);
                    Route::get('/tambah', 'create')->name('create')->can('create', User::class);
                    Route::post('/', 'store')->name('store')->can('create', User::class);

                    Route::whereNumber('user')->group(function () {
                        Route::get('/{user}', 'show')->name('show')->can('view', 'user');
                        Route::get('/{user}/ubah', 'edit')->name('edit')->can('update', 'user');
                        Route::put('/{user}', 'update')->name('update')->can('update', 'user');
                        Route::post('/{user}/reset-kata-sandi', 'resetPassword')->name('reset-password')->can('resetPassword', 'user');
                        Route::patch('/{user}/aktifkan', 'activate')->name('activate')->can('activate', 'user');
                        Route::patch('/{user}/nonaktifkan', 'deactivate')->name('deactivate')->can('deactivate', 'user');
                    });
                });

                Route::controller(Admin\SiteContentController::class)->prefix('konten')->name('content.')->group(function () {
                    Route::get('/', 'edit')->name('edit');
                    Route::put('/', 'update')->name('update');
                    Route::post('/foto-sampul', 'storeCover')->name('cover.store')->middleware('throttle:uploads');
                    Route::delete('/foto-sampul', 'destroyCover')->name('cover.destroy');
                });

                Route::controller(Admin\MasterDataController::class)->prefix('data-master')->name('master.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{type}', 'items')->name('items');
                    Route::post('/{type}', 'store')->name('store');
                    Route::put('/{type}/{id}', 'update')->name('update')->whereNumber('id');
                    Route::delete('/{type}/{id}', 'destroy')->name('destroy')->whereNumber('id');
                });
            });

            Route::prefix('opd')->name('opd.')->middleware('role:opd')->group(function () use ($organizationRoutes) {
                Route::get('/', Opd\DashboardController::class)->name('dashboard');
                $organizationRoutes();
                Route::get('/laporan/{report}', [Opd\MonthlyReportController::class, 'show'])
                    ->name('reports.show')->can('view', 'report');
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

                Route::get('/laporan/{report}', [Kelompok\MonthlyReportController::class, 'show'])
                    ->name('reports.show')->can('view', 'report');
            });
        });
    });
});
