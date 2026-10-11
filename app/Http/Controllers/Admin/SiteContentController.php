<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Publik\SiteImageController;
use App\Http\Requests\Admin\SiteContentRequest;
use App\Http\Requests\Admin\SiteImageRequest;
use App\Models\BusinessSector;
use App\Models\HeroSlide;
use App\Models\SiteSetting;
use App\Support\ImageVariants;
use App\Support\PublicDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SiteContentController extends Controller
{
    /** Tab "Beranda": slide hero, judul + pengantar, foto bagian Tentang. */
    public function edit(): View
    {
        return view('admin.content.home', [
            'settings' => SiteSetting::values(),
            'slides' => HeroSlide::orderBy('position')->orderBy('id')->get(),
        ]);
    }

    /** Tab "Halaman & kontak": foto sampul, foto bidang usaha, Profil Ransiki, kontak kantor. */
    public function editPages(): View
    {
        return view('admin.content.pages', [
            'settings' => SiteSetting::values(),
            'sectors' => BusinessSector::orderBy('name')->get(['id', 'name', 'photo_path']),
        ]);
    }

    public function update(SiteContentRequest $request): RedirectResponse
    {
        SiteSetting::put($request->validated());
        Cache::forget('public.sitemap');

        return back()->with('status', 'Konten website disimpan.');
    }

    /** Foto sampul ("sampul") atau foto bagian Tentang ("tentang"). */
    public function storePhoto(SiteImageRequest $request, string $slot): JsonResponse
    {
        $key = SiteSetting::PHOTO_SLOTS[$slot];
        $path = $this->storeImage($request, 'site/'.$slot, $slot === 'sampul' ? ImageVariants::HERO_BUDGET : []);

        ImageVariants::delete(SiteImageController::DISK, SiteSetting::get($key), ImageVariants::WIDE);
        SiteSetting::put([$key => $path]);

        return response()->json(['message' => SiteSetting::KEYS[$key].' diperbarui.'], 201);
    }

    public function destroyPhoto(string $slot): RedirectResponse
    {
        $key = SiteSetting::PHOTO_SLOTS[$slot];
        ImageVariants::delete(SiteImageController::DISK, SiteSetting::get($key), ImageVariants::WIDE);
        SiteSetting::put([$key => null]);

        return back()->with('status', SiteSetting::KEYS[$key].' dihapus.');
    }

    public function storeSectorPhoto(SiteImageRequest $request, BusinessSector $sector): JsonResponse
    {
        $path = $this->storeImage($request, 'site/bidang-'.$sector->id);

        ImageVariants::delete(SiteImageController::DISK, $sector->photo_path, ImageVariants::WIDE);
        $sector->forceFill(['photo_path' => $path])->save();
        Cache::forget(PublicDirectory::SECTORS_CACHE_KEY);

        return response()->json(['message' => 'Foto bidang usaha diperbarui.'], 201);
    }

    public function destroySectorPhoto(BusinessSector $sector): RedirectResponse
    {
        ImageVariants::delete(SiteImageController::DISK, $sector->photo_path, ImageVariants::WIDE);
        $sector->forceFill(['photo_path' => null])->save();
        Cache::forget(PublicDirectory::SECTORS_CACHE_KEY);

        return back()->with('status', 'Foto bidang usaha dihapus.');
    }

    /** Diproses seperti foto produk (putar sesuai EXIF, tanpa metadata) + varian WebP 480/960/1600. */
    private function storeImage(SiteImageRequest $request, string $prefix, array $budgets = []): string
    {
        $path = $prefix.'-'.Str::uuid().'.jpg';
        ImageVariants::store(SiteImageController::DISK, $request->file('file')->getRealPath(), $path, ImageVariants::WIDE, $budgets);

        return $path;
    }
}
