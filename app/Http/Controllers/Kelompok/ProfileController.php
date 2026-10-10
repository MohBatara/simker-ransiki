<?php

namespace App\Http\Controllers\Kelompok;

use App\Actions\Organizations\SaveOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\LegalStatusRequest;
use App\Http\Requests\Organizations\LicenseRequest;
use App\Http\Requests\Organizations\OrganizationRequest;
use App\Models\License;
use App\Models\Organization;
use App\Support\OrganizationOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman milik kelompok sendiri: profil, produk, legalitas & izin. Kelompok selalu diambil dari akun.
 */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $organization = $this->organization($request)->load(['institutionType', 'village', 'sectors', 'opds', 'updater']);

        return view('kelompok.profile', [
            'organization' => $organization,
            'attachments' => $organization->attachments()->with('uploader')->latest()->get()->each->setRelation('attachable', $organization),
        ]);
    }

    public function edit(Request $request): View
    {
        return view('shared.organizations.form-page', [
            'organization' => $this->organization($request)->load(['sectors', 'opds']),
            'options' => OrganizationOptions::all(),
            'canAdminister' => false,
            'action' => route('kelompok.profile.update'),
            'cancelUrl' => route('kelompok.profile.show'),
        ]);
    }

    public function update(OrganizationRequest $request, SaveOrganization $save): RedirectResponse
    {
        $save->handle($request->user(), $request->organizationData(), $request->targetOrganization());

        return redirect()->route('kelompok.profile.show')->with('status', 'Profil kelompok diperbarui.');
    }

    public function products(Request $request): View
    {
        $organization = $this->organization($request);

        return view('kelompok.products', [
            'organization' => $organization,
            'products' => $organization->products()->orderBy('name')->get(),
        ]);
    }

    public function licenses(Request $request): View
    {
        $organization = $this->organization($request);

        return view('kelompok.licenses', [
            'organization' => $organization,
            'licenses' => $organization->licenses()->withCount('attachments')->orderBy('type')->get(),
            'licenseTypes' => LicenseRequest::SUGGESTED_TYPES,
            'options' => OrganizationOptions::all(),
        ]);
    }

    public function license(Request $request, License $license): View
    {
        return view('shared.organizations.license-detail', [
            'organization' => $this->organization($request),
            'license' => $license,
            'attachments' => $license->attachments()->with('uploader')->latest()->get()->each->setRelation('attachable', $license),
            'backUrl' => route('kelompok.licenses.index'),
            'breadcrumbs' => [
                ['label' => 'Legalitas & Izin', 'href' => route('kelompok.licenses.index')],
                ['label' => $license->type],
            ],
        ]);
    }

    public function updateLegal(LegalStatusRequest $request): RedirectResponse
    {
        $request->targetOrganization()->forceFill([...$request->validated(), 'updated_by' => $request->user()->id])->save();

        return back()->with('status', 'Status legalitas diperbarui.');
    }

    private function organization(Request $request): Organization
    {
        return Organization::findOrFail($request->user()->organization_id);
    }
}
