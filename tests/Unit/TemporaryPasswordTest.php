<?php

use App\Support\TemporaryPassword;

test('kata sandi sementara mudah diketik dan selalu berbeda', function () {
    $passwords = collect(range(1, 50))->map(fn () => TemporaryPassword::generate());

    $passwords->each(fn (string $password) => expect($password)->toMatch('/^[a-hjkmnp-z2-9]{5}-[a-hjkmnp-z2-9]{5}$/'));
    expect($passwords->unique())->toHaveCount(50);
});
