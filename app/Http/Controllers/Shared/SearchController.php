<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cari global di bilah atas (Admin dan OPD): kelompok menurut nama, ketua, atau kampung; produk
 * menurut nama. Hasil dibatasi kelompok yang terlihat pengguna (Organization::visibleTo).
 */
class SearchController extends Controller
{
    public const LIMIT = 20;

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = trim((string) $request->query('q', ''));
        $searching = mb_strlen($query) >= 2;
        $like = '%'.addcslashes($query, '%_\\').'%';

        $organizations = $searching
            ? Organization::visibleTo($user)
                ->where(fn ($where) => $where
                    ->where('organizations.name', 'like', $like)
                    ->orWhere('leader_name', 'like', $like)
                    ->orWhereHas('village', fn ($village) => $village->where('name', 'like', $like)))
                ->with(['institutionType:id,name', 'village:id,name'])
                ->orderBy('name')
                ->limit(self::LIMIT)
                ->get()
            : collect();

        $products = $searching
            ? Product::query()
                ->whereIn('organization_id', Organization::visibleTo($user)->select('organizations.id'))
                ->where('name', 'like', $like)
                ->with('organization:id,name')
                ->orderBy('name')
                ->limit(self::LIMIT)
                ->get()
            : collect();

        return view('shared.search.index', [
            'area' => $user->role->area(),
            'query' => $query,
            'searching' => $searching,
            'organizations' => $organizations,
            'products' => $products,
        ]);
    }
}
