<?php

namespace App\Http\Requests\Concerns;

use App\Support\ImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

/**
 * Foto iPhone (HEIC/HEIF) yang tetap sampai ke server (JavaScript mati) diubah ke JPEG sebelum divalidasi, bila Imagick
 * mendukung HEIC. Yang tidak bisa dikonversi tetap HEIC dan ditolak aturan ImageUpload::notHeic() / AllowedUpload.
 */
trait ConvertsHeicUploads
{
    protected function prepareForValidation(): void
    {
        $files = $this->allFiles();
        $changed = false;

        foreach (Arr::dot($files) as $key => $file) {
            if ($file instanceof UploadedFile && ImageUpload::isHeic($file) && ($jpeg = ImageUpload::convertHeic($file))) {
                Arr::set($files, $key, $jpeg);
                $changed = true;
            }
        }

        if ($changed) {
            $this->files->replace($files);
            $this->convertedFiles = null;
        }
    }
}
