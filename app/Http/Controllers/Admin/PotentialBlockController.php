<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Publik\SiteImageController;
use App\Http\Requests\Admin\PotentialBlockRequest;
use App\Models\SiteSetting;
use App\Support\ImageVariants;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Blok "Potensi ekonomi" di Profil Ransiki (Konten Website > Profil Ransiki). Hanya Admin (route role:admin + request).
 */
class PotentialBlockController extends Controller
{
    public function update(PotentialBlockRequest $request, string $n): RedirectResponse
    {
        $data = $request->validated()[$request->prefix()];
        $values = [
            "potential_{$n}_title" => $data['title'] ?? null,
            "potential_{$n}_text" => $data['text'] ?? null,
            "potential_{$n}_sector" => isset($data['sector']) ? (string) $data['sector'] : null,
        ];

        if ($photo = $request->file($request->prefix().'.photo')) {
            $path = "site/potensi-{$n}-".Str::uuid().'.jpg';
            ImageVariants::store(SiteImageController::DISK, $photo->getRealPath(), $path, ImageVariants::WIDE);
            ImageVariants::delete(SiteImageController::DISK, SiteSetting::get("potential_{$n}_photo_path"), ImageVariants::WIDE);
            $values["potential_{$n}_photo_path"] = $path;
        }

        SiteSetting::put($values);

        return to_route('admin.content.profile')->with('status', "Blok potensi {$n} disimpan.");
    }

    public function destroyPhoto(string $n): RedirectResponse
    {
        ImageVariants::delete(SiteImageController::DISK, SiteSetting::get("potential_{$n}_photo_path"), ImageVariants::WIDE);
        SiteSetting::put(["potential_{$n}_photo_path" => null]);

        return to_route('admin.content.profile')->with('status', "Foto blok potensi {$n} dihapus.");
    }
}
