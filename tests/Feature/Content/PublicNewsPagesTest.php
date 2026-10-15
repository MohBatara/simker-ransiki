<?php

use App\Models\Post;
use App\Models\User;

beforeEach(fn () => $this->travelTo('2026-10-10 09:00:00'));

/** Slug post di daftar "Berita terbaru" (bukan sorotan atau agenda), sesuai urutan tampil. */
function latestSlugs(string $html): array
{
    preg_match('#aria-labelledby="terbaru".*?</section>#s', $html, $section);
    preg_match_all('#<a href="http://localhost/berita/([^"]+)" class="[^"]*after:absolute#', $section[0] ?? '', $links);

    return $links[1];
}

function highlightSlugs(string $html): array
{
    preg_match('#aria-labelledby="sorotan".*?</section>#s', $html, $section);
    preg_match_all('#<a href="http://localhost/berita/([^"]+)" class="[^"]*after:absolute#', $section[0] ?? '', $links);

    return $links[1];
}

test('kop editorial, bar alat menempel dengan tab kategori berjumlah, filter kategori lewat query string', function () {
    Post::factory()->count(3)->create(['category' => 'berita']);
    Post::factory()->count(2)->create(['category' => 'kegiatan']);
    Post::factory()->draft()->create(['category' => 'kegiatan']);

    $html = $this->get('/berita')->assertOk()
        ->assertSee('<title>Kabar Distrik Ransiki · ', false)
        ->assertSee('Berita dan kegiatan terbaru dari Distrik Ransiki')->assertSee('Sabtu, 10 Oktober 2026')
        ->getContent();
    $text = preg_replace('/\s+/', ' ', strip_tags($html));

    expect($text)->toContain('Semua 5')->toContain('Berita 3')->toContain('Kegiatan 2')
        ->and($html)->toContain('aria-current="page"')->toContain('sticky top-13 z-30')->not->toContain('data-reading-progress');

    $kegiatan = $this->get('/berita?kategori=kegiatan')->assertOk()->getContent();
    expect(Post::whereIn('slug', latestSlugs($kegiatan))->pluck('category')->map->value->unique()->all())->toBe(['kegiatan'])
        ->and(latestSlugs($kegiatan))->toHaveCount(2)
        ->and(highlightSlugs($kegiatan))->toBe([]);
});

test('sorotan: berita utama berfoto + dua terbaru, hanya halaman 1 tanpa filter, tidak diulang di daftar terbaru', function () {
    foreach (range(1, 14) as $i) {
        Post::factory()->create(['title' => "Berita urut {$i}", 'published_at' => now()->subHours($i), 'cover_photo_path' => $i === 3 ? 'posts/3/a.jpg' : null]);
    }

    $first = $this->get('/berita')->assertOk()->getContent();
    $highlights = highlightSlugs($first);
    // Berita utama = terbaru yang berfoto (urut 3), lalu dua terbaru lainnya (urut 1, 2).
    expect(collect($highlights)->map(fn (string $slug) => Post::where('slug', $slug)->value('title'))->all())->toBe(['Berita urut 3', 'Berita urut 1', 'Berita urut 2'])
        ->and($first)->toContain('overlay-band-bottom');

    // 14 post = 3 sorotan + 11 di daftar → 2 halaman (10, 1); sorotan tidak pernah muncul di daftar mana pun.
    $second = $this->get('/berita?page=2')->assertOk()->getContent();
    $listed = [...latestSlugs($first), ...latestSlugs($second)];
    expect(latestSlugs($first))->toHaveCount(10)->and(latestSlugs($second))->toHaveCount(1)
        ->and(array_intersect($listed, $highlights))->toBe([])
        ->and(count(array_unique($listed)))->toBe(11)
        ->and(highlightSlugs($second))->toBe([])
        ->and($first)->toContain('aria-label="Halaman"')->toContain('Berikutnya')
        ->toMatch('#bg-brand text-paper tabular-nums" aria-current="page"><span class="sr-only">Halaman </span>1</span>#');
});

test('arsip: bulan dengan jumlah post, filter ?bulan=YYYY-MM, nilai tidak sah diabaikan', function () {
    Post::factory()->count(2)->create(['published_at' => '2026-09-05 10:00:00', 'category' => 'berita']);
    Post::factory()->create(['title' => 'Kegiatan September', 'published_at' => '2026-09-20 10:00:00', 'category' => 'kegiatan']);
    Post::factory()->create(['title' => 'Kabar Agustus', 'published_at' => '2026-08-31 23:30:00']);
    Post::factory()->scheduled()->create(['title' => 'Belum terbit']);

    $html = $this->get('/berita')->getContent();
    preg_match('#aria-labelledby="arsip".*?</section>#s', $html, $archive);
    expect(preg_replace('/\s+/', ' ', strip_tags($archive[0])))->toContain('September 2026 3')->toContain('Agustus 2026 1')
        ->and($archive[0])->toContain('href="'.e(route('public.posts', ['bulan' => '2026-09'])).'"');

    $september = $this->get('/berita?bulan=2026-09')->assertOk()->assertSee('<title>Berita dan kegiatan September 2026 · ', false)->getContent();
    expect(Post::whereIn('slug', latestSlugs($september))->pluck('published_at')->map->format('Y-m')->unique()->all())->toBe(['2026-09'])
        ->and(latestSlugs($september))->toHaveCount(3)
        ->and(latestSlugs($this->get('/berita?bulan=2026-09&kategori=kegiatan')->getContent()))->toHaveCount(1)
        ->and(latestSlugs($this->get('/berita?bulan=2026-08')->getContent()))->toHaveCount(1);
    // Nilai tidak sah = tanpa filter bulan (sorotan tampil lagi).
    expect(highlightSlugs($this->get('/berita?bulan=2026-13')->assertOk()->getContent()))->toHaveCount(3);
});

test('agenda kegiatan: hanya post Kegiatan (maks 8) dengan blok tanggal dan kelompok terkait publik, halaman 1 tanpa filter', function () {
    $organization = App\Models\Organization::factory()->public()->create(['name' => 'Kelompok Contoh Agenda']);
    $hidden = App\Models\Organization::factory()->create(['name' => 'Kelompok Contoh Rahasia', 'is_public' => false]);
    foreach (range(1, 9) as $i) {
        Post::factory()->create(['category' => 'kegiatan', 'published_at' => now()->subDays($i)]);
    }
    $first = Post::where('category', 'kegiatan')->latest('published_at')->first();
    $first->organizations()->attach([$organization->id, $hidden->id]);
    Post::factory()->create(['category' => 'berita', 'title' => 'Bukan kegiatan']);

    $html = $this->get('/berita')->getContent();
    preg_match('#aria-labelledby="agenda".*?</section>#s', $html, $agenda);

    expect(substr_count($agenda[0], '<article'))->toBe(8)
        ->and($agenda[0])->not->toContain('Bukan kegiatan')->toContain('Kelompok Contoh Agenda')->not->toContain('Kelompok Contoh Rahasia')
        ->toContain('<time datetime="2026-10-09"')->toContain('>Okt</span>')->toContain('data-reveal-row');
    expect($this->get('/berita?kategori=kegiatan')->getContent())->not->toContain('aria-labelledby="agenda"');
    Post::query()->where('category', 'kegiatan')->delete();
    expect($this->get('/berita')->getContent())->not->toContain('aria-labelledby="agenda"');
});

test('judul Agenda kegiatan bila ada kegiatan setelah hari ini (paling dekat dulu), selain itu Kegiatan terbaru', function () {
    $past = Post::factory()->create(['category' => 'kegiatan', 'title' => 'Kegiatan lalu', 'published_at' => now()->subDays(2)]);
    $html = $this->get('/berita')->getContent();
    preg_match('#aria-labelledby="agenda".*?</section>#s', $html, $agenda);
    expect($agenda[0])->toContain('>Kegiatan terbaru</h2>')->toContain('Kegiatan kelompok dan pemerintah distrik, terbaru lebih dulu.');

    // Hari ini (10 Oktober) bukan "akan datang"; 20 dan 12 Oktober akan datang, paling dekat dulu.
    Post::factory()->create(['category' => 'kegiatan', 'title' => 'Kegiatan hari ini', 'event_date' => '2026-10-10', 'published_at' => now()->subDays(5)]);
    Post::factory()->create(['category' => 'kegiatan', 'title' => 'Kegiatan jauh', 'event_date' => '2026-10-20', 'published_at' => now()->subDays(4)]);
    Post::factory()->create(['category' => 'kegiatan', 'title' => 'Kegiatan dekat', 'event_date' => '2026-10-12', 'published_at' => now()->subDays(3)]);

    $html = $this->get('/berita')->getContent();
    preg_match('#aria-labelledby="agenda".*?</section>#s', $html, $agenda);
    preg_match_all('#<h3[^>]*>\s*<a[^>]*>([^<]+)</a>#', $agenda[0], $titles);

    expect($agenda[0])->toContain('>Agenda kegiatan</h2>')
        ->and($titles[1])->toBe(['Kegiatan dekat', 'Kegiatan jauh', 'Kegiatan hari ini', 'Kegiatan lalu'])
        // Blok tanggal = tanggal kegiatan (tanpa itu tanggal terbit).
        ->and($agenda[0])->toContain('<time datetime="2026-10-12"')->toContain('<time datetime="2026-10-08"')
        ->toMatch('#<time datetime="2026-10-12"[^>]*h-\[50px\] w-\[46px\][^>]*md:h-14 md:w-13">\s*<span[^>]*>12</span>\s*<span[^>]*>Okt</span>#');
});

test('kartu agenda: judul 2 baris bertinggi tetap, lokasi dua baris (kampung, lalu kelompok dengan title)', function () {
    $village = App\Models\Village::factory()->create(['name' => 'Kampung Contoh Lokasi']);
    $organization = App\Models\Organization::factory()->public()->create(['name' => 'Kelompok Contoh Dengan Nama Yang Cukup Panjang', 'village_id' => $village->id]);
    Post::factory()->create(['category' => 'kegiatan'])->organizations()->attach($organization);

    preg_match('#aria-labelledby="agenda".*?</section>#s', $this->get('/berita')->getContent(), $agenda);

    expect($agenda[0])->toContain('line-clamp-2 min-h-[3.25rem]')
        ->toMatch('#title="Kampung Contoh Lokasi">.*?<span class="truncate">Kampung Contoh Lokasi</span>\s*</p>\s*<p class="[^"]*truncate[^"]*text-ink-soft" title="Kelompok Contoh Dengan Nama Yang Cukup Panjang">#s');
});

test('cari judul (query string), tanpa sorotan; hasil kosong menampilkan pesan dan tombol Lihat semua berita', function () {
    Post::factory()->create(['title' => 'Panen raya kakao di kampung']);
    Post::factory()->create(['title' => 'Pelatihan pengemasan keripik']);
    Post::factory()->create(['title' => 'Diskon 100% bukan berita']);

    $html = $this->get('/berita?q=kakao')->assertOk()->assertSee('Panen raya kakao di kampung')->assertDontSee('Pelatihan pengemasan keripik')->getContent();
    expect(highlightSlugs($html))->toBe([])->and(preg_replace('/\s+/', ' ', strip_tags($html)))->toContain('1 berita');
    // % dan _ diperlakukan sebagai huruf biasa, bukan wildcard.
    $this->get('/berita?q=%25')->assertSee('Diskon 100% bukan berita')->assertDontSee('Panen raya kakao di kampung');

    $this->get('/berita?q=tidak-ada-judul-seperti-ini')->assertOk()
        ->assertSee('Tidak ada berita yang cocok')->assertSee('Lihat semua berita')->assertSee('href="'.route('public.posts').'"', false);
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

    expect($text)->toContain('Kantor Distrik Ransiki 24 September 2026 · 3 menit baca · Diperbarui 2 Oktober 2026')
        ->and($html)->toContain('drop-cap')->toContain('data-reading-progress')->toContain('h-full origin-left scale-x-0 bg-sun')
        ->toContain('aria-label="Berita sebelumnya dan berikutnya"')->toContain('Bagikan ke WhatsApp')
        ->and($text)
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

test('Baca juga: tiga berita, kategori yang sama lebih dulu lalu terbaru', function () {
    $post = Post::factory()->create(['category' => 'kegiatan', 'published_at' => now()->subDays(10)]);
    $sameOld = Post::factory()->create(['category' => 'kegiatan', 'title' => 'Kegiatan lama', 'published_at' => now()->subDays(20)]);
    Post::factory()->count(3)->sequence(fn ($s) => ['title' => 'Berita baru '.$s->index, 'published_at' => now()->subDays($s->index + 1)])->create(['category' => 'berita']);

    $html = $this->get("/berita/{$post->slug}")->getContent();
    preg_match('#aria-labelledby="berita-lainnya".*?</section>#s', $html, $others);
    preg_match_all('#<a href="http://localhost/berita/([^"]+)" class="[^"]*after:absolute#', $others[0], $links);

    expect($links[1])->toHaveCount(3)->and($links[1][0])->toBe($sameOld->slug)
        ->and(Post::where('slug', $links[1][1])->value('title'))->toBe('Berita baru 0')
        ->and($links[1])->not->toContain($post->slug);
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
