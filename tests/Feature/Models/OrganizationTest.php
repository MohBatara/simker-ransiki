<?php

use App\Models\Attachment;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('slug dibuat otomatis dan unik', function () {
    $first = Organization::factory()->create(['name' => 'KUB Contoh Kakao']);
    $second = Organization::factory()->create(['name' => 'KUB Contoh Kakao']);

    expect($first->slug)->toBe('kub-contoh-kakao')
        ->and($second->slug)->toBe('kub-contoh-kakao-2');
});

test('awal wajib lapor selalu tanggal 1', function () {
    $organization = Organization::factory()->create(['reporting_starts_on' => '2026-08-20']);

    expect($organization->fresh()->reporting_starts_on->toDateString())->toBe('2026-08-01');
});

test('kelompok dengan banyak OPD dihitung sekali', function () {
    $opds = Opd::factory()->count(3)->create();
    Organization::factory()->create()->opds()->attach($opds->pluck('id'));
    Organization::factory()->create()->opds()->attach($opds->first()->id);

    $count = DB::table('organizations')
        ->join('organization_opd', 'organization_opd.organization_id', '=', 'organizations.id')
        ->distinct()
        ->count('organizations.id');

    expect($count)->toBe(2);
});

test('kelompok yang punya laporan atau akun tidak bisa dihapus', function () {
    $withReport = Organization::factory()->create();
    MonthlyReport::factory()->for($withReport)->create();
    $withUser = Organization::factory()->create();
    User::factory()->kelompok($withUser)->create();

    expect($withReport->delete())->toBeFalse()
        ->and($withUser->delete())->toBeFalse()
        ->and(Organization::count())->toBe(2);
});

test('menghapus kelompok ikut menghapus file lampiran dan foto produknya', function () {
    Storage::fake('local');

    $organization = Organization::factory()->create();
    $product = Product::factory()->for($organization)->create(['photo_path' => 'products/foto.jpg']);
    Storage::disk('local')->put('products/foto.jpg', 'foto');
    Storage::disk('local')->put('products/foto-kecil.jpg', 'foto kecil');

    $files = [
        Attachment::factory()->create(['attachable_type' => 'organization', 'attachable_id' => $organization->id]),
        Attachment::factory()->create(['attachable_type' => 'product', 'attachable_id' => $product->id]),
    ];
    foreach ($files as $file) {
        Storage::disk('local')->put($file->path, 'isi');
    }

    expect($organization->delete())->toBeTrue();

    expect(Attachment::count())->toBe(0)
        ->and(Product::count())->toBe(0)
        ->and(Storage::disk('local')->exists('products/foto.jpg'))->toBeFalse()
        ->and(Storage::disk('local')->exists('products/foto-kecil.jpg'))->toBeFalse();
    foreach ($files as $file) {
        expect(Storage::disk('local')->exists($file->path))->toBeFalse();
    }
});

test('lampiran disimpan dengan alias morph, bukan nama class', function () {
    $attachment = Attachment::factory()->create();

    expect(DB::table('attachments')->value('attachable_type'))->toBe('organization')
        ->and($attachment->attachable)->toBeInstanceOf(Organization::class);
});
