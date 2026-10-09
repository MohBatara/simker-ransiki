<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\PublicDirectory;
use App\Support\PublicImages;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('publik.home', [
            'stats' => PublicDirectory::stats(),
            'sectors' => PublicDirectory::sectors(),
            'settings' => SiteSetting::values(),
            'cover' => PublicImages::slot('sampul'),
            'about' => PublicImages::slot('tentang'),
            // Produk unggulan yang sudah punya foto didahulukan.
            'featured' => Product::query()->publiclyListed()
                ->where('products.is_featured', true)
                ->with(['organization:id,slug,name', 'organization.sectors:id,name'])
                ->orderByRaw('products.photo_path is null')
                ->orderBy('products.name')
                ->limit(8)
                ->get(['products.id', 'products.organization_id', 'products.name', 'products.unit', 'products.capacity', 'products.photo_path']),
        ]);
    }
}
