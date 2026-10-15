<?php

namespace App\Http\Controllers\Publik;

use App\Enums\LegalStatus;
use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\DirectorySearch;
use App\Support\PublicDirectory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Direktori publik. Query hanya memilih kolom publik (CLAUDE.md Bagian 3), sehingga data internal
 * seperti jumlah anggota, aset, laporan, atau keuangan tidak pernah sampai ke view.
 */
class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = DirectorySearch::fromRequest($request);
        $organizations = $search->results();

        return view('publik.directory.index', [
            'search' => $search,
            'organizations' => $organizations,
            'facets' => $search->facets(),
            'chips' => $search->chips(),
            'total' => PublicDirectory::stats()['organizations'],
        ]);
    }

    public function show(string $slug): View
    {
        $organization = Organization::query()->publiclyListed()
            ->where('slug', $slug)
            ->with([
                'institutionType:id,name',
                'village:id,name',
                'sectors:id,name',
                'products' => fn ($query) => $query->where('is_public', true)->orderBy('name')
                    ->select(['id', 'organization_id', 'slug', 'name', 'description', 'unit', 'capacity', 'photo_path', 'updated_at']),
                // Hanya jenis izin yang sudah terbit; nomor izin tidak pernah tampil publik.
                'licenses' => fn ($query) => $query->where('status', LicenseStatus::Issued)->orderBy('type')->select(['id', 'organization_id', 'type']),
            ])
            ->firstOrFail(['id', 'slug', 'name', 'institution_type_id', 'village_id', 'description', 'founded_year',
                'production_capacity', 'markets', 'legal_status', 'show_contact_publicly', 'status', 'profile_status', 'updated_at']);

        // Kontak baru diambil bila kelompok mengizinkan, jadi tidak pernah ada di memori bila tidak.
        $contact = $organization->show_contact_publicly
            ? Organization::query()->whereKey($organization->id)->first(['leader_name', 'phone', 'email', 'address'])
            : null;

        $updatedAt = collect([$organization->updated_at, $organization->products->max('updated_at')])->filter()->max();

        return view('publik.directory.show', [
            'organization' => $organization,
            'contact' => $contact,
            'showLegal' => $organization->legal_status === LegalStatus::Registered || $organization->licenses->isNotEmpty(),
            'updatedAt' => $updatedAt,
            'ogImage' => $organization->products->firstWhere('photo_path', '!=', null)?->photoUrl(),
        ]);
    }
}
