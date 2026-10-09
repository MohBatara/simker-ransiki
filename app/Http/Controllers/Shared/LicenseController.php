<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\LicenseRequest;
use App\Models\License;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Dipakai Admin/OPD (URL berisi {organization}) dan Kelompok (kelompok dari akun). Hak dicek di
 * LicenseRequest::authorize() dan Policy; data kelompok lain dijawab 404.
 */
class LicenseController extends Controller
{
    public function store(LicenseRequest $request, ?Organization $organization = null): RedirectResponse
    {
        $request->targetOrganization()->licenses()->create($request->validated());

        return back()->with('status', 'Izin ditambahkan.');
    }

    public function update(LicenseRequest $request, ?Organization $organization = null, ?License $license = null): RedirectResponse
    {
        $license->update($request->validated());

        return back()->with('status', 'Izin diperbarui.');
    }

    public function destroy(?Organization $organization = null, ?License $license = null): RedirectResponse
    {
        Gate::authorize('delete', $license);

        $license->delete();

        return back()->with('status', 'Izin dihapus.');
    }
}
