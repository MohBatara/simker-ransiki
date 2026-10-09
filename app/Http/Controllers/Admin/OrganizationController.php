<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Organizations\SaveOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\OrganizationRequest;
use App\Http\Requests\Organizations\OrganizationStatusRequest;
use App\Http\Requests\Organizations\LicenseRequest;
use App\Models\Organization;
use App\Support\OrganizationOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'type', 'village', 'sector', 'opd', 'status']);

        $organizations = Organization::visibleTo($request->user())
            ->filter($filters)
            ->with(['institutionType', 'village', 'sectors', 'opds'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.organizations.index', [
            'organizations' => $organizations,
            'filters' => $filters,
            'options' => OrganizationOptions::all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.organizations.form', [
            'organization' => new Organization(['reporting_starts_on' => now()->startOfMonth()]),
            'options' => OrganizationOptions::all(),
        ]);
    }

    public function store(OrganizationRequest $request, SaveOrganization $save): RedirectResponse
    {
        $data = $request->organizationData();

        if (! $request->boolean('confirm_similar')) {
            $similar = Organization::similarTo($data['name'], $data['village_id']);

            if ($similar->isNotEmpty()) {
                return back()->withInput()->with('similar', $similar->map->only(['id', 'name'])->all());
            }
        }

        $organization = $save->handle($request->user(), $data);

        return redirect()->route('admin.organizations.show', $organization)->with('status', 'Kelompok ditambahkan.');
    }

    public function show(Organization $organization): View
    {
        return $this->detail($organization, 'profil', [
            'organization' => $organization->load(['institutionType', 'village', 'sectors', 'opds', 'updater']),
        ]);
    }

    public function products(Organization $organization): View
    {
        return $this->detail($organization, 'produk', [
            'products' => $organization->products()->orderBy('name')->get(),
        ]);
    }

    public function licenses(Organization $organization): View
    {
        return $this->detail($organization, 'izin', [
            'licenses' => $organization->licenses()->orderBy('type')->get(),
            'licenseTypes' => LicenseRequest::SUGGESTED_TYPES,
        ]);
    }

    public function reports(Organization $organization): View
    {
        return $this->detail($organization, 'laporan', [
            'reports' => $organization->monthlyReports()->orderByDesc('period')->paginate(12),
        ]);
    }

    public function users(Organization $organization): View
    {
        return $this->detail($organization, 'akun', [
            'users' => $organization->users()->orderBy('name')->get(),
        ]);
    }

    public function edit(Organization $organization): View
    {
        return view('admin.organizations.form', [
            'organization' => $organization->load(['sectors', 'opds']),
            'options' => OrganizationOptions::all(),
        ]);
    }

    public function update(OrganizationRequest $request, Organization $organization, SaveOrganization $save): RedirectResponse
    {
        $data = $request->organizationData();
        $identityChanged = $data['name'] !== $organization->name || (int) $data['village_id'] !== $organization->village_id;

        $save->handle($request->user(), $data, $organization);

        $similar = $identityChanged ? Organization::similarTo($organization->name, $organization->village_id, $organization->id) : collect();

        return redirect()->route('admin.organizations.show', $organization)
            ->with('status', 'Profil kelompok diperbarui.')
            ->with('similar', $similar->map->only(['id', 'name'])->all());
    }

    public function updateStatus(OrganizationStatusRequest $request, Organization $organization): RedirectResponse
    {
        $organization->forceFill(['status' => $request->validated('status'), 'updated_by' => $request->user()->id])->save();

        return back()->with('status', 'Status kelompok diubah menjadi '.lcfirst($organization->status->label()).'.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        // Model menolak penghapusan bila kelompok sudah punya laporan atau akun.
        if (! $organization->delete()) {
            return back()->with('error', 'Kelompok ini sudah punya laporan atau akun, jadi tidak bisa dihapus. Tandai sebagai ditutup.');
        }

        return redirect()->route('admin.organizations.index')->with('status', 'Kelompok dihapus.');
    }

    private function detail(Organization $organization, string $tab, array $data): View
    {
        $organization->loadMissing(['institutionType', 'village'])
            ->loadCount(['monthlyReports', 'users', 'products', 'licenses', 'opds']);

        return view('admin.organizations.show', ['organization' => $organization, 'tab' => $tab, ...$data]);
    }
}
