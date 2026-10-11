<?php

use Illuminate\Support\Facades\Blade;

test('x-link: tautan berdiri sendiri setinggi 44px di HP; di dalam kalimat bergaris bawah', function () {
    $standalone = Blade::render('<x-link href="/a">Lihat semua</x-link>');
    $inline = Blade::render('<x-link href="/a" inline>Produk</x-link>');
    $button = Blade::render('<x-link x-on:click="go()">Pakai jumlah ini</x-link>');

    expect($standalone)->toContain('<a href="/a"')->toContain('min-h-11')->toContain('md:min-h-0')->not->toContain('underline underline-offset-2')
        ->and($inline)->toContain('underline')->not->toContain('min-h-11')
        ->and($button)->toContain('<button type="button"');
});

test('x-list: baris dengan tautan bisa diklik seluruhnya, min 44px, chevron di kanan', function () {
    $html = Blade::render('<x-list flush><x-list.item href="/b">Satu</x-list.item><x-list.item>Dua</x-list.item></x-list>');

    expect($html)->toContain('<a href="/b"')->toContain('min-h-11')->toContain('px-4 md:px-5')
        ->and(substr_count($html, '<svg'))->toBe(1);
});

test('x-breadcrumbs: halaman terakhir ditandai, hanya di layar lebar', function () {
    $html = Blade::render('<x-breadcrumbs :items="[[\'label\' => \'Semua Kelompok\', \'href\' => \'/k\'], [\'label\' => \'KUB A\']]" />');

    expect($html)->toContain('aria-label="Remah"')->toContain('max-md:hidden')
        ->toContain('<a href="/k"')->toContain('aria-current="page"');
});

test('tombol tutup alert dan modal setinggi 44px di HP', function () {
    expect(Blade::render('<x-alert dismissible>Tersimpan.</x-alert>'))->toContain('size-11')
        ->and(Blade::render('<x-modal name="m" title="Judul">Isi</x-modal>'))->toContain('size-11');
});

test('kontrol form menerima invalid-if untuk form Alpine', function () {
    $html = Blade::render('<x-input invalid-if="error(\'costs\')" />');

    expect($html)->toContain('x-bind:aria-invalid="!! (error(&#039;costs&#039;))"')
        ->toContain('border-fix! focus:border-fix! focus:ring-fix!');
    expect(Blade::render('<x-textarea />'))->not->toContain('x-bind:aria-invalid');
});
