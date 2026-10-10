<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Organizations\SaveOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\LicenseRequest;
use App\Http\Requests\Organizations\OrganizationRequest;
use App\Models\License;
use App\Models\Organization;
use App\Support\OrganizationOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daftar dan detail kelompok untuk Admin dan OPD. Isi daftar disaring scope visibleTo; hak per kelompok
 * dicek Policy di route. Route name diawali area pengguna: admin.organizations.* atau opd.organizations.*.
 */
class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'type', 'village', 'sector', 'opd', 'status', 'akun', 'laporan', 'periode']);

        $query = Organization::visibleTo($request->user())->filter($filters);

        // Ringkasan hasil ("25 kelompok · 3 tidak aktif") dihitung dari daftar yang sudah tersaring.
        $statusCounts = (clone $query)->toBase()->selectRaw('organizations.status, COUNT(*) as total')->groupBy('organizations.status')->pluck('total', 'status');

        $organizations = $query
            ->with(['institutionType', 'village', 'sectors', 'opds'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('shared.organizations.index', [
            'area' => $this->area($request),
            'organizations' => $organizations,
            'statusCounts' => ['inactive' => (int) ($statusCounts['inactive'] ?? 0), 'closed' => (int) ($statusCounts['closed'] ?? 0)],
            'filters' => $filters,
            'options' => OrganizationOptions::all(),
        ]);
    }

    public function show(Request $request, Organization $organization): View
    {
        return $this->detail($request, $organization, 'profil', [
            'organization' => $organization->load(['institutionType', 'village', 'sectors', 'opds', 'updater']),
            'attachments' => $organization->attachments()->with('uploader')->latest()->get()->each->setRelation('attachable', $organization),
        ]);
    }

    public function products(Request $request, Organization $organization): View
    {
        return $this->detail($request, $organization, 'produk', [
            'products' => $organization->products()->orderBy('name')->get(),
        ]);
    }

    public function licenses(Request $request, Organization $organization): View
    {
        return $this->detail($request, $organization, 'izin', [
            'licenses' => $organization->licenses()->withCount('attachments')->orderBy('type')->get(),
            'licenseTypes' => LicenseRequest::SUGGESTED_TYPES,
        ]);
    }

    public function license(Request $request, Organization $organization, License $license): View
    {
        $area = $this->area($request);

        return view('shared.organizations.license-detail', [
            'organization' => $organization,
            'license' => $license,
            'attachments' => $license->attachments()->with('uploader')->latest()->get()->each->setRelation('attachable', $license),
            'backUrl' => route($area.'.organizations.licenses', $organization),
            'breadcrumbs' => [
                ['label' => $area === 'opd' ? 'Kelompok Binaan' : 'Semua Kelompok', 'href' => route($area.'.organizations.index')],
                ['label' => $organization->name, 'href' => route($area.'.organizations.show', $organization)],
                ['label' => 'Izin', 'href' => route($area.'.organizations.licenses', $organization)],
                ['label' => $license->type],
            ],
        ]);
    }

    public function reports(Request $request, Organization $organization): View
    {
        return $this->detail($request, $organization, 'laporan', [
            'reports' => $organization->monthlyReports()->orderByDesc('period')->paginate(12),
        ]);
    }

    public function users(Request $request, Organization $organization): View
    {
        return $this->detail($request, $organization, 'akun', [
            'users' => $organization->users()->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, Organization $organization): View
    {
        $area = $this->area($request);

        return view('shared.organizations.form-page', [
            'organization' => $organization->load(['sectors', 'opds']),
            'options' => OrganizationOptions::all(),
            'canAdminister' => $request->user()->can('administer', $organization),
            'action' => route($area.'.organizations.update', $organization),
            'cancelUrl' => route($area.'.organizations.show', $organization),
        ]);
    }

    public function update(OrganizationRequest $request, Organization $organization, SaveOrganization $save): RedirectResponse
    {
        $data = $request->organizationData();
        $identityChanged = array_key_exists('name', $data)
            && ($data['name'] !== $organization->name || (int) $data['village_id'] !== $organization->village_id);

        $save->handle($request->user(), $data, $organization);

        $similar = $identityChanged ? Organization::similarTo($organization->name, $organization->village_id, $organization->id) : collect();

        return redirect()->route($this->area($request).'.organizations.show', $organization)
            ->with('status', 'Profil kelompok diperbarui.')
            ->with('similar', $similar->map->only(['id', 'name'])->all());
    }

    private function detail(Request $request, Organization $organization, string $tab, array $data): View
    {
        $organization->loadMissing(['institutionType', 'village', 'opds:id,name,short_name'])
            ->loadCount(['monthlyReports', 'users', 'products', 'licenses', 'opds']);

        return view('shared.organizations.detail', [
            'area' => $this->area($request),
            'organization' => $organization,
            // Fakta kunci di header: laporan terakhir (status + periode).
            'latestReport' => $organization->monthlyReports()->orderByDesc('period')->first(),
            'tab' => $tab,
            ...$data,
        ]);
    }

    /** "admin" atau "opd"; dipakai sebagai awalan nama route. */
    private function area(Request $request): string
    {
        return $request->user()->role->area();
    }
}
