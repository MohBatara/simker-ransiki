<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Files\UploadRequest;
use Illuminate\Auth\Access\Response;

/**
 * Foto sampul, foto "Tentang", dan foto bidang usaha: aturan file sama dengan foto produk.
 */
class SiteImageRequest extends UploadRequest
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
