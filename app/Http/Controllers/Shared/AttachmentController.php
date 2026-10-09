<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Files\StoreAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Files\UploadRequest;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lampiran kelompok dan izin. Hak lihat/hapus mengikuti induknya (AttachmentPolicy, dicek di route).
 */
class AttachmentController extends Controller
{
    public function store(UploadRequest $request, StoreAttachment $store): JsonResponse
    {
        $attachment = $store->handle($request->target(), $request->file('file'), $request->user());

        return response()->json(['message' => 'File diunggah.', 'id' => $attachment->id], 201);
    }

    /** Tampil di browser. PDF dibuka dalam sandbox agar skrip di dalamnya tidak berjalan. */
    public function show(Attachment $attachment): StreamedResponse
    {
        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, $this->headers($attachment), 'inline');
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, $this->headers($attachment));
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $attachment->delete();

        return back()->with('status', 'File dihapus.');
    }

    private function headers(Attachment $attachment): array
    {
        return [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
            'Cache-Control' => 'private, no-store',
        ];
    }
}
