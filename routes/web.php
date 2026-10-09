<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Kelompok;
use App\Http\Controllers\Opd;
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
