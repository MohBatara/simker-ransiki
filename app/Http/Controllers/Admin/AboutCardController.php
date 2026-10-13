<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Publik\SiteImageController;
use App\Http\Requests\Admin\AboutCardRequest;
use App\Models\SiteSetting;
use App\Support\ImageVariants;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Kartu foto bagian "Mengenal Distrik Ransiki" (Konten Website > Beranda). Hanya Admin (route role:admin + request).
 */
class AboutCardController extends Controller
{
    public function update(AboutCardRequest $request, string $n): RedirectResponse
    {
        $data = $request->validated()[$request->prefix()];
        $values = [
            "about_card_{$n}_title" => $data['title'] ?? null,
            "about_card_{$n}_text" => $data['text'] ?? null,
            "about_card_{$n}_sector" => isset($data['sector']) ? (string) $data['sector'] : null,
        ];

        if ($photo = $request->file($request->prefix().'.photo')) {
            $path = "site/kartu-{$n}-".Str::uuid().'.jpg';
            ImageVariants::store(SiteImageController::DISK, $photo->getRealPath(), $path, ImageVariants::HERO, ImageVariants::HERO_SLIDE_BUDGET, jpegMaxSide: ImageVariants::HERO_MAX_SIDE);
            ImageVariants::delete(SiteImageController::DISK, SiteSetting::get("about_card_{$n}_photo_path"), ImageVariants::HERO);
            $values["about_card_{$n}_photo_path"] = $path;
        }

        SiteSetting::put($values);

        return to_route('admin.content.edit')->with('status', "Kartu {$n} disimpan.");
    }

    public function destroyPhoto(string $n): RedirectResponse
    {
        ImageVariants::delete(SiteImageController::DISK, SiteSetting::get("about_card_{$n}_photo_path"), ImageVariants::HERO);
        SiteSetting::put(["about_card_{$n}_photo_path" => null]);

        return to_route('admin.content.edit')->with('status', "Foto kartu {$n} dihapus.");
    }
}
