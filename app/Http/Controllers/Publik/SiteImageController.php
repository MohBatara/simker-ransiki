<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\BusinessSector;
use App\Models\SiteSetting;
use App\Support\ImageVariants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto lebar website publik (sampul, "Tentang", bidang usaha). Selalu publik; ?ukuran=480|960|1600&format=webp.
 */
class SiteImageController extends Controller
{
    public const DISK = 'local';

    public function slot(Request $request, string $slot): StreamedResponse
    {
        return $this->respond($request, SiteSetting::get(SiteSetting::PHOTO_SLOTS[$slot]));
    }

    public function sector(Request $request, BusinessSector $sector): StreamedResponse
    {
        return $this->respond($request, $sector->photo_path);
    }

    private function respond(Request $request, ?string $jpegPath): StreamedResponse
    {
        abort_unless($jpegPath && Storage::disk(self::DISK)->exists($jpegPath), 404);

        [$path, $mime] = ImageVariants::resolve(self::DISK, $jpegPath, $request->query('ukuran'), $request->query('format'), ImageVariants::WIDE);

        return Storage::disk(self::DISK)->response($path, null, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            // URL berisi ?v= yang berubah setiap foto diganti.
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
