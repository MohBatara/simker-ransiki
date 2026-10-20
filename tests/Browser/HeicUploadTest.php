<?php

use App\Models\Attachment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * Foto iPhone (.heic) dipilih di laptop: accept memuat HEIC, foto diubah ke JPG di browser (heic-to, dimuat saat itu
 * saja), pratinjau muncul, lalu tersimpan sebagai JPG. Jalankan per file setelah `sail npm run build`:
 * sail php vendor/bin/pest tests/Browser/HeicUploadTest.php
 *
 * Server tes pest-plugin-browser belum meneruskan unggahan multipart ("@TODO files" di LaravelHttpServer), jadi file
 * yang akan dikirim uploader ditangkap di halaman, lalu file yang SAMA dikirim ke route unggah lewat request tes Laravel.
 */
beforeEach(function () {
    Storage::fake('local');
    $this->organization = Organization::factory()->create(['name' => 'KUB Foto Peramban']);
    $this->actingAs(User::factory()->admin()->create());
});

test('pilih file .heic: accept memuat HEIC, pratinjau muncul, yang dikirim JPG, tersimpan sebagai JPG', function () {
    $page = visit("/admin/kelompok/{$this->organization->id}");
    $input = 'input[data-image-accept="document"]';

    // Chromium desktop (bukan iPhone/iPad): HEIC boleh dipilih.
    $page->assertScript("document.querySelector('{$input}').accept.includes('.heic')", true);

    // Tangkap file yang dikirim uploader (XHR tidak diteruskan ke server tes).
    $page->script(<<<'JS'
        () => {
            XMLHttpRequest.prototype.send = function (data) {
                const file = data.get('file');
                const reader = new FileReader();
                reader.onload = () => {
                    window.__terkirim = { name: file.name, type: file.type, data: reader.result.split(',')[1] };
                };
                reader.readAsDataURL(file);
            };
        }
        JS);

    // Pilih file: plugin tidak menerima path lokal, jadi isi .heic dikirim ke halaman lalu dipasang lewat DataTransfer +
    // event change (sama dengan memilih file di dialog). Tipe sengaja kosong seperti kiriman WhatsApp/Drive.
    $base64 = base64_encode(file_get_contents(base_path('tests/fixtures/foto-iphone.heic')));
    $page->script(<<<JS
        () => {
            const bytes = Uint8Array.from(atob('{$base64}'), (c) => c.charCodeAt(0));
            const transfer = new DataTransfer();
            transfer.items.add(new File([bytes], 'foto-iphone.heic', { type: '' }));
            const input = document.querySelector('{$input}');
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        JS);

    for ($i = 0; $i < 40 && ! $page->script('() => !! window.__terkirim'); $i++) {
        $page->wait(0.5);
    }

    // Pratinjau tampil di daftar unggahan, nama sudah .jpg.
    $page->assertScript("!! document.querySelector('[x-data^=\"uploader\"] li img[src^=\"blob:\"]')", true)
        ->assertSee('foto-iphone.jpg')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();

    $sent = $page->script('() => window.__terkirim');
    $jpeg = base64_decode($sent['data']);
    [$width, $height, $type] = getimagesizefromstring($jpeg);

    expect($sent['name'])->toBe('foto-iphone.jpg')
        ->and($sent['type'])->toBe('image/jpeg')
        ->and($type)->toBe(IMAGETYPE_JPEG)
        ->and(max($width, $height))->toBeLessThanOrEqual(2000);

    // File yang sama tersimpan sebagai JPG lewat route unggah lampiran.
    $this->postJson("/lampiran/kelompok/{$this->organization->id}", ['file' => realUpload($sent['name'], $jpeg)])->assertCreated();

    $attachment = Attachment::firstOrFail();
    expect($attachment->mime_type)->toBe('image/jpeg')
        ->and($attachment->original_name)->toBe('foto-iphone.jpg')
        ->and(getimagesizefromstring(Storage::disk('local')->get($attachment->path))[2])->toBe(IMAGETYPE_JPEG);
});
