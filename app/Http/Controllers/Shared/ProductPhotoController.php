<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Files\StoreProductPhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Files\UploadRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductPhotoController extends Controller
{
    /**
     * Tanpa login bila produk dan kelompoknya tampil publik; selain itu wajib login dan berhak melihat.
     * Pengunjung tanpa hak mendapat 404 agar keberadaan foto tidak terbongkar.
     */
    public function show(Request $request, Product $product): StreamedResponse
    {
        abort_unless($product->photo_path, 404);

        $public = $product->isPubliclyVisible();
        if (! $public) {
            $user = $request->user();
            abort_unless($user?->is_active && Gate::forUser($user)->allows('view', $product), 404);
        }

        $path = $request->query('ukuran') === 'kecil' ? $product->thumbnailPath() : $product->photo_path;

        return Storage::disk(Product::PHOTO_DISK)->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            // URL berisi ?v= yang berubah setiap foto diganti, jadi aman di-cache lama.
            'Cache-Control' => $public ? 'public, max-age=604800' : 'private, max-age=3600',
        ]);
    }

    public function store(UploadRequest $request, Product $product, StoreProductPhoto $store): JsonResponse
    {
        $store->handle($product, $request->file('file'));

        return response()->json(['message' => 'Foto produk diperbarui.'], 201);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->deletePhotoFiles();
        $product->forceFill(['photo_path' => null])->save();

        return back()->with('status', 'Foto produk dihapus.');
    }
}
