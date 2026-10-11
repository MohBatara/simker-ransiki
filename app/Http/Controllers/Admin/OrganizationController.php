<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Organizations\ChangeOrganizationStatus;
use App\Actions\Organizations\SaveOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\OrganizationRequest;
use App\Http\Requests\Organizations\OrganizationStatusRequest;
use App\Models\Organization;
use App\Support\OrganizationOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Tambah, ubah status, dan hapus kelompok (khusus Admin). Daftar, detail, dan ubah profil ada di
 * Shared\OrganizationController karena dipakai juga oleh OPD.
 */
class OrganizationController extends Controller
{
    public function create(): View
    {
        return view('shared.organizations.form-page', [
            'organization' => new Organization(['reporting_starts_on' => now()->startOfMonth()]),
            'options' => OrganizationOptions::all(),
            'canAdminister' => true,
            'action' => route('admin.organizations.store'),
            'cancelUrl' => route('admin.organizations.index'),
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

    public function updateStatus(OrganizationStatusRequest $request, Organization $organization, ChangeOrganizationStatus $change): RedirectResponse
    {
        $organization = $change->handle($request->user(), $organization, $request->status(), $request->validated('reason'));

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
}
