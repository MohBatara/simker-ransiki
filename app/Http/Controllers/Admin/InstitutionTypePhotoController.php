<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Publik\SiteImageController;
use App\Http\Requests\Admin\InstitutionTypePhotoRequest;
use App\Models\InstitutionType;
use App\Support\ImageVariants;
use App\Support\PublicDirectory;
use App\Support\PublicImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Foto kartu jenis kelembagaan (Data Master > Jenis kelembagaan > Foto). Hanya Admin (route role:admin + request).
 */
class InstitutionTypePhotoController extends Controller
{
    public function edit(InstitutionType $institutionType): View
    {
        return view('admin.master.type-photo', [
            'type' => $institutionType,
            'current' => PublicImages::institutionTypePhoto($institutionType),
        ]);
    }

    public function store(InstitutionTypePhotoRequest $request, InstitutionType $institutionType): RedirectResponse
    {
        $path = 'site/jenis-'.$institutionType->id.'-'.Str::uuid().'.jpg';
        ImageVariants::store(SiteImageController::DISK, $request->file('photo')->getRealPath(), $path, ImageVariants::HERO, ImageVariants::HERO_SLIDE_BUDGET, jpegMaxSide: ImageVariants::HERO_MAX_SIDE);

        ImageVariants::delete(SiteImageController::DISK, $institutionType->photo_path, ImageVariants::HERO);
        $institutionType->forceFill(['photo_path' => $path])->save();
        Cache::forget(PublicDirectory::TYPES_CACHE_KEY);

        return back()->with('status', 'Foto jenis kelembagaan disimpan.');
    }

    public function destroy(InstitutionType $institutionType): RedirectResponse
    {
        ImageVariants::delete(SiteImageController::DISK, $institutionType->photo_path, ImageVariants::HERO);
        $institutionType->forceFill(['photo_path' => null])->save();
        Cache::forget(PublicDirectory::TYPES_CACHE_KEY);

        return back()->with('status', 'Foto jenis kelembagaan dihapus.');
    }
}
