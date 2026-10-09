<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Publik\CoverPhotoController;
use App\Http\Requests\Admin\CoverPhotoRequest;
use App\Http\Requests\Admin\SiteContentRequest;
use App\Models\SiteSetting;
use App\Support\ImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SiteContentController extends Controller
{
    public function edit(): View
    {
        return view('admin.content', ['settings' => SiteSetting::values()]);
    }

    public function update(SiteContentRequest $request): RedirectResponse
    {
        SiteSetting::put($request->validated());
        Cache::forget('public.sitemap');

        return back()->with('status', 'Konten website disimpan.');
    }

    /** Diproses seperti foto produk: diputar sesuai EXIF, maks 1600 px, tanpa metadata, JPEG. */
    public function storeCover(CoverPhotoRequest $request): JsonResponse
    {
        $photo = ImageProcessor::process($request->file('file')->getRealPath(), $request->file('file')->getMimeType(), ImageProcessor::MAX_SIDE, 'jpeg');
        $path = 'site/cover-'.Str::uuid().'.jpg';
        Storage::disk(CoverPhotoController::DISK)->put($path, $photo['contents']);

        $this->deleteCoverFile();
        SiteSetting::put(['cover_photo_path' => $path]);

        return response()->json(['message' => 'Foto sampul diperbarui.'], 201);
    }

    public function destroyCover(): RedirectResponse
    {
        $this->deleteCoverFile();
        SiteSetting::put(['cover_photo_path' => null]);

        return back()->with('status', 'Foto sampul dihapus.');
    }

    private function deleteCoverFile(): void
    {
        if ($old = SiteSetting::get('cover_photo_path')) {
            Storage::disk(CoverPhotoController::DISK)->delete($old);
        }
    }
}
