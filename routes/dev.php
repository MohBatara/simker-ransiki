<?php

use App\Http\Controllers\Dev\ComponentGalleryController;
use Illuminate\Support\Facades\Route;

// Dimuat hanya saat APP_ENV=local (lihat bootstrap/app.php).
Route::prefix('dev/komponen')->name('dev.components.')->controller(ComponentGalleryController::class)->group(function () {
    Route::get('/', 'app')->name('app');
    Route::get('/publik', 'public')->name('public');
    Route::get('/masuk', 'guest')->name('guest');
});
