<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

// Meniru boot di APP_ENV=local, yang memuat routes/dev.php lewat bootstrap/app.php.
function enableDevRoutes(): void
{
    app()->detectEnvironment(fn () => 'local');
    Route::middleware('web')->group(base_path('routes/dev.php'));
    app('router')->getRoutes()->refreshNameLookups();
}

$pages = [
    'app' => ['/dev/komponen', 'Badge status'],
    'publik' => ['/dev/komponen/publik', 'Direktori kelompok'],
    'masuk' => ['/dev/komponen/masuk', 'Username atau email'],
];

test('route komponen tidak terdaftar di luar environment local', function (string $url) {
    expect(Route::has('dev.components.app'))->toBeFalse();

    $this->get($url)->assertNotFound();
})->with(array_map(fn ($page) => [$page[0]], $pages));

test('halaman komponen dapat dibuka di environment local', function (string $url, string $text) {
    enableDevRoutes();

    $this->get($url)->assertOk()->assertSee($text);
})->with($pages);

test('sidebar memakai label ringkas dengan nama menu lengkap untuk tooltip', function () {
    enableDevRoutes();

    $this->get('/dev/komponen')
        ->assertOk()
        ->assertSee('aria-label="Legalitas &amp; Izin"', false)
        ->assertSee('Izin')
        ->assertSee('Dasbor')
        ->assertDontSee('Dashboard');
});

test('bar aksi hanya menempel di bawah layar HP bila diminta', function () {
    $sticky = Blade::render('<x-form-actions sticky><x-button>Simpan</x-button></x-form-actions>');
    $plain = Blade::render('<x-form-actions><x-button>Simpan</x-button></x-form-actions>');

    expect($sticky)->toContain('max-md:sticky')
        ->and($plain)->not->toContain('max-md:sticky');
});
