<?php

namespace App\Support\Imports;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * File impor yang menunggu pratinjau, di disk privat `local` (storage/app/private/imports). Setiap
 * unggahan punya token acak dan hanya bisa dibuka oleh Admin yang mengunggahnya. Setelah diimpor, file
 * sumber dihapus dan hasilnya (termasuk baris gagal) disimpan di file meta yang sama. File lebih dari
 * satu hari dibersihkan saat ada unggahan baru.
 */
class ImportFile
{
    private const DIR = 'imports';

    public static function store(UploadedFile $file, string $type, User $user): string
    {
        self::prune();

        $token = (string) Str::uuid();
        Storage::disk('local')->putFileAs(self::DIR, $file, "{$token}.{$type}");
        self::writeMeta($token, ['user_id' => $user->id, 'type' => $type, 'name' => $file->getClientOriginalName(), 'result' => null]);

        return $token;
    }

    /** Meta unggahan milik pengguna ini; null bila token tidak ada atau milik orang lain. */
    public static function meta(string $token, User $user): ?array
    {
        if (! Str::isUuid($token) || ! Storage::disk('local')->exists(self::metaPath($token))) {
            return null;
        }

        $meta = json_decode(Storage::disk('local')->get(self::metaPath($token)), true);

        return ($meta['user_id'] ?? null) === $user->id ? $meta : null;
    }

    public static function sourcePath(string $token, array $meta): ?string
    {
        $path = self::DIR."/{$token}.{$meta['type']}";

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }

    /** Menyimpan hasil impor dan menghapus file sumber, agar token yang sama tidak bisa diimpor dua kali. */
    public static function finish(string $token, array $meta, array $result): void
    {
        self::writeMeta($token, [...$meta, 'result' => $result]);
        Storage::disk('local')->delete(self::DIR."/{$token}.{$meta['type']}");
    }

    public static function discard(string $token, array $meta): void
    {
        Storage::disk('local')->delete([self::DIR."/{$token}.{$meta['type']}", self::metaPath($token)]);
    }

    private static function writeMeta(string $token, array $meta): void
    {
        Storage::disk('local')->put(self::metaPath($token), json_encode($meta));
    }

    private static function metaPath(string $token): string
    {
        return self::DIR."/{$token}.json";
    }

    private static function prune(): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files(self::DIR) as $path) {
            if ($disk->lastModified($path) < now()->subDay()->getTimestamp()) {
                $disk->delete($path);
            }
        }
    }
}
