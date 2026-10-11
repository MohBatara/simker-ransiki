<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\BusinessSector;
use App\Models\HeroSlide;
use App\Models\SiteSetting;
use App\Support\ImageVariants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto lebar website publik (sampul, "Tentang", bidang usaha). Selalu publik; ?ukuran=480|960|1600&format=webp.
 * Slide hero: ?ukuran=640|1280|1920&format=webp; slide nonaktif hanya untuk Admin (pratinjau).
 */
class SiteImageController extends Controller
{
    public const DISK = 'local';

    public const DEFAULT_HERO = 'hero-bawaan.jpg';

    public function slot(Request $request, string $slot): StreamedResponse
    {
        return $this->respond($request, SiteSetting::get(SiteSetting::PHOTO_SLOTS[$slot]));
    }

    public function sector(Request $request, BusinessSector $sector): StreamedResponse
    {
        return $this->respond($request, $sector->photo_path);
    }

    public function hero(Request $request, HeroSlide $slide): StreamedResponse
    {
        abort_unless($slide->is_active || $request->user()?->isAdmin(), 404);

        return $this->respond($request, $slide->photo_path, ImageVariants::HERO);
    }

    /** Foto hero bawaan dari repo (resources/aset) selama belum ada slide aktif. */
    public function heroDefault(Request $request): BinaryFileResponse
    {
        $widths = ImageVariants::HERO;
        $file = $request->query('format') === 'webp' && in_array((int) $request->query('ukuran'), $widths, true)
            ? ImageVariants::path(self::DEFAULT_HERO, (int) $request->query('ukuran'))
            : self::DEFAULT_HERO;

        return response()->file(resource_path('aset/'.$file), [
            'Content-Type' => str_ends_with($file, '.webp') ? 'image/webp' : 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    private function respond(Request $request, ?string $jpegPath, array $widths = ImageVariants::WIDE): StreamedResponse
    {
        abort_unless($jpegPath && Storage::disk(self::DISK)->exists($jpegPath), 404);

        [$path, $mime] = ImageVariants::resolve(self::DISK, $jpegPath, $request->query('ukuran'), $request->query('format'), $widths);

        return Storage::disk(self::DISK)->response($path, null, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            // URL berisi ?v= yang berubah setiap foto diganti.
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
