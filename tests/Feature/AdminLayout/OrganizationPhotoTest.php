<?php

use App\Models\Attachment;
use App\Models\BusinessSector;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\OrganizationPhoto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Cache::flush();
    $this->opd = Opd::factory()->create();
    $this->organization = Organization::factory()->create(['name' => 'KUB Foto Contoh']);
    $this->organization->opds()->attach($this->opd);
});

test('foto utama: foto produk, lalu lampiran gambar, lalu foto bidang usaha; foto sampul situs tidak dipakai', function () {
    SiteSetting::put(['cover_photo_path' => 'site/sampul.jpg']);
    expect(OrganizationPhoto::for($this->organization))->toBeNull();

    $sector = BusinessSector::factory()->create(['photo_path' => 'site/bidang.jpg']);
    $this->organization->sectors()->attach($sector);
    expect(OrganizationPhoto::for($this->organization))->toMatchArray(['source' => 'sector'])
        ->and(OrganizationPhoto::for($this->organization)['url'])->toContain('/foto-bidang/'.$sector->id);

    $attachment = Attachment::factory()->for($this->organization, 'attachable')->create(['mime_type' => 'image/jpeg']);
    expect(OrganizationPhoto::for($this->organization))->toMatchArray(['source' => 'attachment', 'url' => route('attachments.show', $attachment)]);

    $product = Product::factory()->for($this->organization)->create(['photo_path' => 'products/foto.jpg']);
    expect(OrganizationPhoto::for($this->organization))->toMatchArray(['source' => 'product', 'url' => $product->photoUrl()]);
});

test('popup pratinjau berisi URL foto utama yang benar, dan URL itu bisa dibuka Admin dan OPD pembina saja', function () {
    $product = Product::factory()->for($this->organization)->create(['photo_path' => 'products/foto.jpg', 'is_public' => false]);
    Storage::disk(Product::PHOTO_DISK)->put($product->photo_path, imageBytes(800, 450));
    $url = $product->photoUrl();

    $admin = User::factory()->admin()->create();
    $html = $this->actingAs($admin)->get('/admin/kelompok?pilih='.$this->organization->id)->assertOk()->getContent();
    preg_match('#<dialog[^>]*data-modal="organization-preview".*?</dialog>#s', $html, $dialog);

    // Dimuat langsung (tanpa lazy) karena popup awalnya tersembunyi.
    expect($dialog[0])->toContain('src="'.e($url).'"')
        ->toContain('data-organization-photo')
        ->not->toMatch('#data-organization-photo[^>]*loading="lazy"|loading="lazy"[^>]*data-organization-photo#');

    // Potongan HTML yang dimuat saat baris diklik berisi URL yang sama.
    $this->get("/admin/kelompok/{$this->organization->id}/ringkas")->assertOk()->assertSee('src="'.e($url).'"', false);

    $this->get($url)->assertOk();
    $this->actingAs(User::factory()->opd($this->opd)->create())->get($url)->assertOk();
    $this->actingAs(User::factory()->opd(Opd::factory()->create())->create())->get($url)->assertNotFound();
    auth()->logout();
    $this->get($url)->assertNotFound();
});

test('tanpa foto: kotak brand-50 dengan inisial dan "Belum ada foto"', function () {
    $this->actingAs(User::factory()->admin()->create())->get("/admin/kelompok/{$this->organization->id}/ringkas")->assertOk()
        ->assertSee('data-organization-photo-empty', false)
        ->assertSee('Belum ada foto')
        ->assertSee('>KF</span>', false);
});

test('kepala detail kelompok memakai foto utama bila ada, inisial bila tidak ada', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get("/admin/kelompok/{$this->organization->id}")->assertOk()
        ->assertDontSee('data-organization-photo ', false)
        ->assertSee('>KF</span>', false);

    $product = Product::factory()->for($this->organization)->create(['photo_path' => 'products/foto.jpg']);
    $this->get("/admin/kelompok/{$this->organization->id}")->assertOk()
        ->assertSee('src="'.e($product->photoUrl()).'"', false)
        ->assertSee('data-organization-photo', false);
});
