<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Kelompok;
use App\Http\Controllers\Opd;
use App\Models\License;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('active')->group(function () {
        Route::get('/akun/kata-sandi', [PasswordController::class, 'edit'])->name('account.password.edit');
        Route::put('/akun/kata-sandi', [PasswordController::class, 'update'])->name('account.password.update');

        Route::middleware('password.changed')->group(function () {
            Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
                Route::get('/', Admin\DashboardController::class)->name('dashboard');

                Route::controller(Admin\OrganizationController::class)->prefix('kelompok')->name('organizations.')->group(function () {
                    Route::get('/', 'index')->name('index')->can('viewAny', Organization::class);
                    Route::get('/tambah', 'create')->name('create')->can('create', Organization::class);
                    Route::post('/', 'store')->name('store')->can('create', Organization::class);

                    Route::whereNumber('organization')->group(function () {
                        Route::get('/{organization}', 'show')->name('show')->can('view', 'organization');
                        Route::get('/{organization}/produk', 'products')->name('products')->can('view', 'organization');
                        Route::get('/{organization}/izin', 'licenses')->name('licenses')->can('view', 'organization');
                        Route::get('/{organization}/laporan', 'reports')->name('reports')->can('view', 'organization');
                        Route::get('/{organization}/akun', 'users')->name('users')->can('view', 'organization');
                        Route::get('/{organization}/ubah', 'edit')->name('edit')->can('update', 'organization');
                        Route::put('/{organization}', 'update')->name('update')->can('update', 'organization');
                        Route::patch('/{organization}/status', 'updateStatus')->name('status')->can('administer', 'organization');
                        Route::delete('/{organization}', 'destroy')->name('destroy')->can('delete', 'organization');
                    });
                });

                // scopeBindings: produk/izin harus milik kelompok di URL, jika tidak 404.
                Route::prefix('kelompok/{organization}')->name('organizations.')->whereNumber('organization')->scopeBindings()->group(function () {
                    Route::post('/produk', [Admin\ProductController::class, 'store'])->name('products.store')->can('create', [Product::class, 'organization']);
                    Route::put('/produk/{product}', [Admin\ProductController::class, 'update'])->name('products.update')->can('update', 'product');
                    Route::delete('/produk/{product}', [Admin\ProductController::class, 'destroy'])->name('products.destroy')->can('delete', 'product');
                    Route::post('/izin', [Admin\LicenseController::class, 'store'])->name('licenses.store')->can('create', [License::class, 'organization']);
                    Route::put('/izin/{license}', [Admin\LicenseController::class, 'update'])->name('licenses.update')->can('update', 'license');
                    Route::delete('/izin/{license}', [Admin\LicenseController::class, 'destroy'])->name('licenses.destroy')->can('delete', 'license');
                });

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

                Route::controller(Admin\MasterDataController::class)->prefix('data-master')->name('master.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{type}', 'items')->name('items');
                    Route::post('/{type}', 'store')->name('store');
                    Route::put('/{type}/{id}', 'update')->name('update')->whereNumber('id');
                    Route::delete('/{type}/{id}', 'destroy')->name('destroy')->whereNumber('id');
                });
            });

            Route::prefix('opd')->name('opd.')->middleware('role:opd')->group(function () {
                Route::get('/', Opd\DashboardController::class)->name('dashboard');
                Route::get('/kelompok/{organization}', [Opd\OrganizationController::class, 'show'])
                    ->name('organizations.show')->can('view', 'organization');
                Route::get('/kelompok/{organization}/ubah', [Opd\OrganizationController::class, 'edit'])
                    ->name('organizations.edit')->can('update', 'organization');
                Route::get('/laporan/{report}', [Opd\MonthlyReportController::class, 'show'])
                    ->name('reports.show')->can('view', 'report');
            });

            Route::prefix('kelompok')->name('kelompok.')->middleware('role:kelompok')->group(function () {
                Route::get('/', Kelompok\DashboardController::class)->name('dashboard');
                Route::get('/laporan/{report}', [Kelompok\MonthlyReportController::class, 'show'])
                    ->name('reports.show')->can('view', 'report');
            });
        });
    });
});
