<?php

use App\Models\Post;
use App\Models\User;

function newsSection(string $html): ?string
{
    return preg_match('#aria-labelledby="berita-kegiatan".*?</section>#s', $html, $match) ? $match[0] : null;
}

test('berita utama = post terbit terbaru yang berfoto; daftar 4 berikutnya tanpa mengulang berita utama', function () {
    $this->travelTo('2026-10-10 09:00:00');
    $withPhoto = Post::factory()->create(['title' => 'Berita berfoto', 'cover_photo_path' => 'posts/1/sampul.jpg', 'published_at' => now()->subDays(5)]);
    foreach (range(1, 5) as $i) {
        Post::factory()->create(['title' => "Berita tanpa foto {$i}", 'published_at' => now()->subDays($i)]);
    }
    Post::factory()->draft()->create(['title' => 'Draf tidak tampil']);

    $section = newsSection($this->get('/')->assertOk()->getContent());
    [$lead, $list] = explode('<ul', $section, 2);
    preg_match_all('#<li class="([^"]*)">.*?<h3[^>]*><a[^>]*>([^<]+)</a></h3>#s', $list, $items);

    expect($lead)->toContain('Berita berfoto')->toContain("/foto-berita/{$withPhoto->id}")
        ->toContain('lg:col-span-7')->toContain('lg:aspect-[16/10]')->toContain('Baca selengkapnya')
        ->and($items[2])->toBe(['Berita tanpa foto 1', 'Berita tanpa foto 2', 'Berita tanpa foto 3', 'Berita tanpa foto 4'])
        ->and($items[1][3])->toContain('max-lg:hidden')
        ->and($items[1][2])->not->toContain('max-lg:hidden')
        ->and($section)->not->toContain('Draf tidak tampil')->not->toContain('KABAR DISTRIK')->not->toContain('Kabar distrik')
        ->toContain('Berita dan kegiatan')->toContain('rounded-full bg-forest');
});

test('baris meta: kategori, tanggal lengkap, dan waktu baca (200 kata per menit, minimal 1); daftar memakai tanggal singkat', function () {
    $this->travelTo('2026-10-10 09:00:00');
    Post::factory()->create(['category' => 'kegiatan', 'body' => str_repeat('kata ', 450), 'published_at' => '2026-09-24 10:00:00', 'cover_photo_path' => 'posts/1/a.jpg']);
    Post::factory()->create(['category' => 'berita', 'body' => 'Isi pendek.', 'published_at' => '2026-09-20 10:00:00']);

    $section = newsSection($this->get('/')->getContent());
    $text = preg_replace('/\s+/', ' ', strip_tags($section));

    expect($text)->toContain('Kegiatan · 24 September 2026 · 3 menit baca')
        ->toContain('Berita · 20 Sep 2026');
});

test('post tanpa foto: panel cream berikon kategori (Berita koran, Kegiatan kalender)', function () {
    Post::factory()->create(['category' => 'kegiatan', 'published_at' => now()->subDay()]);
    Post::factory()->create(['category' => 'berita', 'published_at' => now()->subDays(2)]);

    $section = newsSection($this->get('/')->getContent());
    [$lead, $list] = explode('<ul', $section, 2);

    expect($lead)->toContain('bg-cream')->toContain('size-20')->not->toContain('<picture')
        ->and($list)->toContain('size-7');
    // Ikon: kalender untuk Kegiatan (berita utama), koran untuk Berita (daftar).
    expect(App\Enums\PostCategory::Activity->icon())->toBe('calendar-days')
        ->and(App\Enums\PostCategory::News->icon())->toBe('newspaper');
});

test('satu post: berita utama lebar penuh tanpa daftar; tanpa post terbit: bagian disembunyikan', function () {
    $this->get('/')->assertDontSee('id="berita-kegiatan"', false);

    Post::factory()->draft()->create();
    $this->get('/')->assertDontSee('id="berita-kegiatan"', false);

    Post::factory()->create(['title' => 'Hanya satu berita']);
    $section = newsSection($this->get('/')->getContent());
    expect($section)->toContain('lg:col-span-12')->not->toContain('<ul');
});

test('judul berita paling banyak 90 karakter di form Admin', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/admin/website/berita', ['title' => str_repeat('a', 91), 'category' => 'berita', 'body' => 'Isi.', 'status' => 'draft'])
        ->assertSessionHasErrors(['title' => 'Judul paling banyak 90 karakter. Ringkas judulnya agar tetap mudah dibaca di Beranda.']);

    $this->get('/admin/website/berita/tambah')->assertSee('maxlength="90"', false);
});
