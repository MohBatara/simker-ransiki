<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('public.sitemap', 3600, function () {
            $organizations = Organization::query()->publiclyListed()->orderBy('name')->get(['slug', 'updated_at']);

            $products = Product::query()->publiclyListed()->orderBy('products.name')->get(['products.slug', 'products.updated_at']);

            $posts = Post::query()->publiclyListed()->latest('published_at')->get(['slug', 'updated_at']);

            return view('publik.sitemap', ['organizations' => $organizations, 'products' => $products, 'posts' => $posts])->render();
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
