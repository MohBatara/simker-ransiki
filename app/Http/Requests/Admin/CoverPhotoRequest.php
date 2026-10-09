<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Files\UploadRequest;
use Illuminate\Auth\Access\Response;

/**
 * Foto sampul beranda: aturan file sama dengan foto produk (JPG/PNG, 10 MB, maks 8000 px).
 */
class CoverPhotoRequest extends UploadRequest
{
    public function authorize(): Response
    {
        return $this->user()?->isAdmin() ? Response::allow() : Response::deny();
    }

    protected function kind(): string
    {
        return 'photo';
    }
}
