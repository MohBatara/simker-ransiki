<?php

namespace App\Actions\Files;

use App\Models\Attachment;
use App\Models\User;
use App\Support\ImageProcessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreAttachment
{
    public const DISK = 'local';

    public const MAX_FILES = 10;

    public function handle(Model $attachable, UploadedFile $file, User $uploader): Attachment
    {
        if ($attachable->attachments()->count() >= self::MAX_FILES) {
            throw ValidationException::withMessages([
                'file' => 'Sudah ada '.self::MAX_FILES.' file. Hapus file lama untuk menambah yang baru.',
            ]);
        }

        $mime = $file->getMimeType();

        if (str_starts_with($mime, 'image/')) {
            $processed = ImageProcessor::process($file->getRealPath(), $mime);
        } else {
            $processed = ['contents' => $file->get(), 'mime' => $mime, 'extension' => 'pdf'];
        }

        $path = sprintf('attachments/%s/%d/%s.%s', $attachable->getMorphClass(), $attachable->getKey(), Str::uuid(), $processed['extension']);
        Storage::disk(self::DISK)->put($path, $processed['contents']);

        return $attachable->attachments()->create([
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => self::displayName($file->getClientOriginalName(), $processed['extension']),
            'mime_type' => $processed['mime'],
            'size' => strlen($processed['contents']),
            'uploaded_by' => $uploader->id,
        ]);
    }

    /** Nama asli hanya untuk tampilan dan nama unduhan; dibersihkan dari karakter yang tidak aman. */
    private static function displayName(string $original, string $extension): string
    {
        $base = Str::of(pathinfo($original, PATHINFO_FILENAME))->ascii()->replaceMatches('/[^A-Za-z0-9 ._()-]+/', '')->squish()->limit(120, '');

        return ($base->isEmpty() ? 'lampiran' : (string) $base).'.'.$extension;
    }
}
