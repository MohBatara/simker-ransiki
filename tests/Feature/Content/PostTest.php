<?php

use App\Models\Organization;
use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('hanya berita Terbit dengan tanggal terbit sudah lewat yang tampil publik', function () {
    $published = Post::factory()->create(['title' => 'Panen Kakao Bersama']);
    $draft = Post::factory()->draft()->create(['title' => 'Draf Rahasia']);
    $scheduled = Post::factory()->scheduled()->create(['title' => 'Berita Terjadwal']);

    $this->get('/berita')->assertOk()->assertSee('Panen Kakao Bersama')->assertDontSee('Draf Rahasia')->assertDontSee('Berita Terjadwal');
    $this->get('/')->assertOk()->assertSee('Panen Kakao Bersama')->assertDontSee('Draf Rahasia')->assertDontSee('Berita Terjadwal');
    $this->get('/sitemap.xml')->assertSee('/berita/'.$published->slug)->assertDontSee($draft->slug)->assertDontSee($scheduled->slug);

    $this->get('/berita/'.$published->slug)->assertOk();
    $this->get('/berita/'.$draft->slug)->assertNotFound();
    $this->get('/berita/'.$scheduled->slug)->assertNotFound();

    // Tepat waktunya tiba (WIT), berita terjadwal tampil.
    $this->travelTo($scheduled->published_at->addMinute());
    $this->get('/berita/'.$scheduled->slug)->assertOk();
});

test('Markdown berbahaya dibersihkan: script, HTML mentah, dan tautan javascript:', function () {
    $post = Post::factory()->create([
        'body' => "Paragraf **tebal**.\n\n<script>alert('xss')</script>\n\n<img src=x onerror=alert(1)>\n\n[klik](javascript:alert(1)) dan [aman](https://contoh.test)",
    ]);

    $html = $this->get('/berita/'.$post->slug)->assertOk()->getContent();

    expect($html)->toContain('<strong>tebal</strong>')
        ->not->toContain("<script>alert('xss')")
        ->not->toContain('onerror=alert')
        ->not->toContain('href="javascript:')
        ->toContain('href="https://contoh.test"');

    $this->actingAs($this->admin)
        ->postJson('/admin/website/berita/pratinjau', ['body' => "<script>alert(1)</script>\n\n[x](javascript:alert(1))"])
        ->assertOk()
        ->assertDontSee('<script>', false)
        ->assertDontSee('javascript:', false);
});

test('halaman berita: kelompok terkait hanya yang publik, plus meta Open Graph', function () {
    $public = Organization::factory()->public()->create(['name' => 'KUB Terkait Publik']);
    $hidden = Organization::factory()->create(['name' => 'KUB Terkait Tersembunyi']);
    $post = Post::factory()->create(['title' => 'Pelatihan Pengolahan', 'summary' => 'Ringkasan untuk WhatsApp.']);
    $post->organizations()->attach([$public->id, $hidden->id]);

    $this->get('/berita/'.$post->slug)
        ->assertOk()
        ->assertSee('KUB Terkait Publik')
        ->assertSee('/direktori/'.$public->slug)
        ->assertDontSee('KUB Terkait Tersembunyi')
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:title" content="Pelatihan Pengolahan', false)
        ->assertSee('<meta name="description" content="Ringkasan untuk WhatsApp.">', false);
});

test('filter kategori dan menu Berita di website publik', function () {
    Post::factory()->create(['title' => 'Kabar Berita', 'category' => 'berita']);
    Post::factory()->create(['title' => 'Kabar Kegiatan', 'category' => 'kegiatan']);

    $this->get('/berita?kategori=kegiatan')->assertOk()->assertSee('Kabar Kegiatan')->assertDontSee('Kabar Berita');
    $this->get('/')->assertSee('href="'.route('public.posts').'"', false);
});

test('Admin membuat, mengubah, dan menghapus berita; slug dibuat dari judul', function () {
    $organization = Organization::factory()->public()->create();
    $this->actingAs($this->admin);

    $this->get('/admin/website/berita/tambah')->assertOk();
    $this->post('/admin/website/berita', [
        'title' => 'Panen Raya Kakao 2026', 'category' => 'kegiatan', 'body' => 'Isi.',
        'status' => 'published', 'organizations' => [$organization->id],
    ])->assertSessionHasNoErrors();

    $post = Post::sole();
    expect($post->slug)->toBe('panen-raya-kakao-2026')
        ->and($post->published_at)->not->toBeNull()
        ->and($post->organizations->pluck('id')->all())->toBe([$organization->id]);

    $this->get('/admin/website/berita')->assertOk()->assertSee('Panen Raya Kakao 2026');
    $this->put("/admin/website/berita/{$post->id}", [
        'title' => 'Panen Raya', 'slug' => 'panen-raya', 'category' => 'berita', 'body' => 'Isi baru.',
        'status' => 'draft', 'published_at' => '',
    ])->assertSessionHasNoErrors();
    expect($post->fresh())->slug->toBe('panen-raya')->published_at->toBeNull()
        ->and($post->organizations()->count())->toBe(0);

    $this->delete("/admin/website/berita/{$post->id}")->assertRedirect('/admin/website/berita');
    expect(Post::count())->toBe(0);
});

test('OPD dan kelompok tidak bisa membuka pengelolaan berita', function () {
    $post = Post::factory()->create();

    $this->actingAs(User::factory()->opd()->create())->get('/admin/website/berita')->assertForbidden();
    $this->actingAs(User::factory()->kelompok(Organization::factory()->create())->create())
        ->put("/admin/website/berita/{$post->id}", ['title' => 'x'])->assertForbidden();
});

test('foto sampul diunggah Admin; foto berita draf tidak bisa dibuka tamu', function () {
    $post = Post::factory()->draft()->create();
    $this->actingAs($this->admin)
        ->post("/admin/website/berita/{$post->id}/foto", ['file' => realUpload('sampul.jpg', imageBytes(1200, 800))])
        ->assertCreated();

    expect($post->fresh()->cover_photo_path)->toStartWith('posts/');
    $this->get(route('public.post-photo', $post))->assertOk();

    auth()->logout();
    $this->get(route('public.post-photo', $post))->assertNotFound();

    $post->update(['status' => 'published', 'published_at' => now()->subHour()]);
    $this->get(route('public.post-photo', $post))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('tanggal kegiatan: diisi Admin hanya untuk kategori Kegiatan, kategori lain dikosongkan; OPD tidak bisa', function () {
    $payload = ['title' => 'Pelatihan contoh', 'body' => 'Isi.', 'status' => 'published'];

    $this->actingAs($this->admin)->get('/admin/website/berita/tambah')->assertOk()->assertSee('Tanggal kegiatan')->assertSee('name="event_date"', false);

    $this->post('/admin/website/berita', [...$payload, 'category' => 'kegiatan', 'event_date' => '2026-11-12'])->assertSessionHasNoErrors();
    $post = Post::where('title', 'Pelatihan contoh')->first();
    expect($post->event_date->toDateString())->toBe('2026-11-12');

    $this->put("/admin/website/berita/{$post->id}", [...$payload, 'category' => 'kegiatan', 'event_date' => 'bukan-tanggal'])->assertSessionHasErrors('event_date');
    // Pindah ke kategori Berita: tanggal kegiatan diabaikan dan dikosongkan.
    $this->put("/admin/website/berita/{$post->id}", [...$payload, 'category' => 'berita', 'event_date' => '2026-12-01'])->assertSessionHasNoErrors();
    expect($post->fresh()->event_date)->toBeNull();

    $this->actingAs(User::factory()->opd()->create())->put("/admin/website/berita/{$post->id}", [...$payload, 'category' => 'kegiatan', 'event_date' => '2026-11-12'])->assertForbidden();
});
