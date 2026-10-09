<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Opd;
use App\Models\SiteSetting;
use App\Support\PublicDirectory;
use Illuminate\View\View;

/**
 * Halaman berisi teks dari Konten Website: Profil Ransiki dan Kontak.
 */
class PageController extends Controller
{
    public function profile(): View
    {
        return view('publik.profile', [
            'settings' => SiteSetting::values(),
            'updatedAt' => SiteSetting::query()->whereIn('key', ['profile_body', 'economic_potential'])->max('updated_at'),
            'cover' => \App\Support\PublicImages::slot('sampul'),
            'stats' => PublicDirectory::stats(),
            'sectors' => PublicDirectory::sectors(),
        ]);
    }

    public function contact(): View
    {
        return view('publik.contact', [
            'settings' => SiteSetting::values(),
            'opds' => Opd::orderBy('name')->get(['name', 'description']),
        ]);
    }
}
