<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('public.sitemap', 3600, function () {
            $organizations = Organization::query()->publiclyListed()->orderBy('name')->get(['slug', 'updated_at']);

            return view('publik.sitemap', ['organizations' => $organizations])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** Area login dan file privat tidak perlu diindeks mesin pencari. */
    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        foreach (['/admin', '/opd', '/kelompok', '/lampiran', '/akun', '/login', '/dev'] as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
