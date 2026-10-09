<?php

namespace App\Http\Controllers\Publik;

use App\Enums\LegalStatus;
use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Organization;
use App\Models\Village;
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
        $filters = [
            'q' => trim((string) $request->query('q')),
            'jenis' => $request->integer('jenis') ?: null,
            'bidang' => $request->integer('bidang') ?: null,
            'kampung' => $request->integer('kampung') ?: null,
        ];

        $organizations = Organization::query()->publiclyListed()
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('organizations.name', 'like', '%'.$filters['q'].'%')
                ->orWhereHas('products', fn ($query) => $query->where('is_public', true)->where('name', 'like', '%'.$filters['q'].'%'))))
            ->when($filters['jenis'], fn ($query, $id) => $query->where('institution_type_id', $id))
            ->when($filters['kampung'], fn ($query, $id) => $query->where('village_id', $id))
            ->when($filters['bidang'], fn ($query, $id) => $query->whereHas('sectors', fn ($query) => $query->whereKey($id)))
            ->with([
                'institutionType:id,name',
                'village:id,name',
                'sectors:id,name',
                'products' => fn ($query) => $query->where('is_public', true)->orderBy('name')->select(['id', 'organization_id', 'name', 'photo_path']),
            ])
            ->orderBy('organizations.name')
            ->select(['organizations.id', 'organizations.slug', 'organizations.name', 'organizations.institution_type_id', 'organizations.village_id', 'organizations.description'])
            ->paginate(20)
            ->withQueryString();

        return view('publik.directory.index', [
            'organizations' => $organizations,
            'filters' => $filters,
            'options' => [
                'jenis' => InstitutionType::orderBy('name')->pluck('name', 'id')->all(),
                'bidang' => BusinessSector::orderBy('name')->pluck('name', 'id')->all(),
                'kampung' => Village::orderBy('name')->pluck('name', 'id')->all(),
            ],
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
                    ->select(['id', 'organization_id', 'name', 'description', 'unit', 'capacity', 'photo_path', 'updated_at']),
                // Hanya jenis izin yang sudah terbit; nomor izin tidak pernah tampil publik.
                'licenses' => fn ($query) => $query->where('status', LicenseStatus::Issued)->orderBy('type')->select(['id', 'organization_id', 'type']),
            ])
            ->firstOrFail(['id', 'slug', 'name', 'institution_type_id', 'village_id', 'description', 'founded_year',
                'production_capacity', 'markets', 'legal_status', 'show_contact_publicly', 'updated_at']);

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
