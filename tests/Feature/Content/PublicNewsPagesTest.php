<?php

use App\Models\Post;
use App\Models\User;

beforeEach(fn () => $this->travelTo('2026-10-10 09:00:00'));

test('daftar berita: tab kategori dengan jumlah, berita utama di halaman 1, filter kategori lewat query string', function () {
    Post::factory()->count(3)->create(['category' => 'berita']);
    Post::factory()->count(2)->create(['category' => 'kegiatan']);
    Post::factory()->draft()->create(['category' => 'kegiatan']);

    $html = $this->get('/berita')->assertOk()->getContent();
    $text = preg_replace('/\s+/', ' ', strip_tags($html));

    expect($text)->toContain('Semua 5')->toContain('Berita 3')->toContain('Kegiatan 2')
        ->and($html)->toContain('aria-current="page"')->toContain('lg:col-span-7');

    $kegiatan = $this->get('/berita?kategori=kegiatan')->assertOk()->getContent();
    preg_match_all('#<a href="http://localhost/berita/([^"]+)" class="[^"]*after:absolute#', $kegiatan, $links);
    expect(Post::whereIn('slug', $links[1])->pluck('category')->map->value->unique()->all())->toBe(['kegiatan'])
        ->and($links[1])->toHaveCount(2);
});

test('cari judul (query string), tanpa berita utama; hasil kosong menampilkan pesan dan tombol Lihat semua berita', function () {
    Post::factory()->create(['title' => 'Panen raya kakao di kampung']);
    Post::factory()->create(['title' => 'Pelatihan pengemasan keripik']);
    Post::factory()->create(['title' => 'Diskon 100% bukan berita']);

    $this->get('/berita?q=kakao')->assertOk()->assertSee('Panen raya kakao di kampung')->assertDontSee('Pelatihan pengemasan keripik')
        ->assertSee('1 berita cocok dengan', false)->assertDontSee('lg:col-span-7', false);
    // % dan _ diperlakukan sebagai huruf biasa, bukan wildcard.
    $this->get('/berita?q=%25')->assertSee('Diskon 100% bukan berita')->assertDontSee('Panen raya kakao di kampung');

    $this->get('/berita?q=tidak-ada-judul-seperti-ini')->assertOk()
        ->assertSee('Tidak ada berita yang cocok')->assertSee('Lihat semua berita')->assertSee('href="'.route('public.posts').'"', false);
});

test('paginasi bernomor 12 per halaman, berita utama tidak diulang di grid', function () {
    foreach (range(1, 26) as $i) {
        Post::factory()->create(['title' => "Berita urut {$i}", 'published_at' => now()->subHours($i)]);
    }

    $first = $this->get('/berita')->assertOk()->getContent();
    preg_match_all('#<li class="min-w-0">#', $first, $cards);
    expect($cards[0])->toHaveCount(12)
        ->and($first)->toContain('aria-label="Halaman"')->toContain('Berikutnya')->toContain('Sebelumnya')
        ->toMatch('#bg-brand text-paper tabular-nums" aria-current="page"><span class="sr-only">Halaman </span>1</span>#');

    // 26 post = 1 berita utama + 25 di grid → 3 halaman (12, 12, 1).
    $third = $this->get('/berita?page=3')->assertOk()->getContent();
    preg_match_all('#<li class="min-w-0">#', $third, $cards);
    expect($cards[0])->toHaveCount(1)->and($third)->not->toContain('lg:col-span-7');
});

test('draf dan terjadwal 404 untuk publik', function () {
    $draft = Post::factory()->draft()->create();
    $scheduled = Post::factory()->scheduled()->create();

    $this->get("/berita/{$draft->slug}")->assertNotFound();
    $this->get("/berita/{$scheduled->slug}")->assertNotFound();
});

test('detail berita: meta + waktu baca, JSON-LD NewsArticle, diperbarui, bagikan, sebelumnya/berikutnya', function () {
    $older = Post::factory()->create(['title' => 'Berita lebih lama', 'published_at' => '2026-09-01 10:00:00']);
    $post = Post::factory()->create([
        'title' => 'Pelatihan fermentasi kakao', 'category' => 'kegiatan', 'summary' => 'Ringkasan pembuka berita.',
        'body' => str_repeat('kata ', 401), 'published_at' => '2026-09-24 10:00:00', 'cover_photo_path' => 'posts/1/a.jpg',
        'photo_alt' => 'Petani menjemur biji kakao', 'photo_caption' => 'Penjemuran di Kampung Contoh 1',
    ]);
    $post->forceFill(['updated_at' => '2026-10-02 08:00:00'])->saveQuietly();
    $newer = Post::factory()->create(['title' => 'Berita lebih baru', 'published_at' => '2026-10-05 10:00:00']);

    $html = $this->get("/berita/{$post->slug}")->assertOk()->getContent();
    $text = preg_replace('/\s+/', ' ', strip_tags($html));
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $ld);
    $json = json_decode($ld[1], true);

    expect($text)->toContain('Kegiatan · 24 September 2026 · 3 menit baca')
        ->toContain('Diterbitkan oleh Kantor Distrik Ransiki · Diperbarui 2 Oktober 2026')
        ->toContain('Penjemuran di Kampung Contoh 1')->toContain('Ringkasan pembuka berita.')
        ->and($html)->toContain('alt="Petani menjemur biji kakao"')
        ->toContain('https://wa.me/?text=')->toContain('https://www.facebook.com/sharer/sharer.php?u='.rawurlencode(route('public.post', $post->slug)))
        ->toContain('Salin tautan')->toContain('data-print-article')
        ->and($json)->toMatchArray(['@type' => 'NewsArticle', 'headline' => 'Pelatihan fermentasi kakao', 'datePublished' => $post->published_at->toAtomString()])
        ->and($json['publisher']['name'])->toBe('Kantor Distrik Ransiki')
        ->and($json['image'][0])->toContain("/foto-berita/{$post->id}")
        ->and($json)->toHaveKey('dateModified');

    // Sebelumnya = lebih lama, Berikutnya = lebih baru.
    expect($html)->toMatch('#href="'.preg_quote(route('public.post', $older->slug), '#').'".*?Sebelumnya.*?Berita lebih lama#s')
        ->toMatch('#href="'.preg_quote(route('public.post', $newer->slug), '#').'".*?Berikutnya.*?Berita lebih baru#s');
});

test('Diperbarui tidak tampil bila diubah di hari yang sama dengan terbit', function () {
    $post = Post::factory()->create(['published_at' => '2026-09-24 08:00:00']);
    $post->forceFill(['updated_at' => '2026-09-24 15:00:00'])->saveQuietly();

    $this->get("/berita/{$post->slug}")->assertOk()->assertDontSee('Diperbarui');
});

test('waktu baca: 200 kata per menit, minimal 1', function () {
    expect(Post::factory()->make(['body' => 'satu dua'])->readingMinutes())->toBe(1)
        ->and(Post::factory()->make(['body' => str_repeat('kata ', 200)])->readingMinutes())->toBe(1)
        ->and(Post::factory()->make(['body' => str_repeat('kata ', 201)])->readingMinutes())->toBe(2);
});

test('validasi Admin: ringkasan maks 200, teks alternatif wajib bila berita berfoto', function () {
    $admin = User::factory()->admin()->create();
    $post = Post::factory()->create(['cover_photo_path' => 'posts/1/a.jpg']);
    $payload = ['title' => 'Judul', 'category' => 'berita', 'body' => 'Isi.', 'status' => 'published'];

    $this->actingAs($admin)->post('/admin/berita', [...$payload, 'summary' => str_repeat('a', 201)])
        ->assertSessionHasErrors(['summary' => 'Ringkasan paling banyak 200 karakter. Cukup satu atau dua kalimat.']);
    $this->put("/admin/berita/{$post->id}", [...$payload, 'photo_alt' => ''])
        ->assertSessionHasErrors(['photo_alt' => 'Isi teks alternatif foto sampul: jelaskan isi foto untuk pembaca layar.']);
    $this->put("/admin/berita/{$post->id}", [...$payload, 'photo_alt' => 'Petani menjemur kakao', 'photo_caption' => 'Keterangan'])
        ->assertSessionHasNoErrors();

    expect($post->fresh()->only(['photo_alt', 'photo_caption']))->toBe(['photo_alt' => 'Petani menjemur kakao', 'photo_caption' => 'Keterangan']);
});
