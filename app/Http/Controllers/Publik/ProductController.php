<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\PublicDirectory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const COLUMNS = ['products.id', 'products.organization_id', 'products.name', 'products.unit', 'products.capacity', 'products.photo_path'];

    public function __invoke(Request $request): View
    {
        $sector = $request->integer('bidang') ?: null;

        $query = fn () => Product::query()->publiclyListed()
            ->when($sector, fn ($query, $id) => $query->whereHas('organization.sectors', fn ($query) => $query->whereKey($id)))
            ->with('organization:id,slug,name');

        return view('publik.products', [
            'sector' => $sector,
            'sectors' => PublicDirectory::sectors(),
            'featured' => $query()->where('products.is_featured', true)->orderByRaw('products.photo_path is null')->orderBy('products.name')->get(self::COLUMNS),
            'others' => $query()->where('products.is_featured', false)->orderBy('products.name')->paginate(24, self::COLUMNS)->withQueryString(),
        ]);
    }
}
