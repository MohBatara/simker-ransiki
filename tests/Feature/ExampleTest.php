<?php

test('halaman beranda dapat dibuka', function () {
    $this->get('/')->assertOk();
});

test('aplikasi memakai zona waktu WIT dan bahasa Indonesia', function () {
    expect(config('app.timezone'))->toBe('Asia/Jayapura')
        ->and(app()->getLocale())->toBe('id');
});
