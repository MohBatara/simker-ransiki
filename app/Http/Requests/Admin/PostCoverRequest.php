<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Files\UploadRequest;
use Illuminate\Auth\Access\Response;

/** Foto sampul berita: aturan file sama dengan foto produk. */
class PostCoverRequest extends UploadRequest
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
