<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\LicenseRequest;
use App\Models\License;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class LicenseController extends Controller
{
    public function store(LicenseRequest $request, Organization $organization): RedirectResponse
    {
        $organization->licenses()->create($request->validated());

        return back()->with('status', 'Izin ditambahkan.');
    }

    public function update(LicenseRequest $request, Organization $organization, License $license): RedirectResponse
    {
        $license->update($request->validated());

        return back()->with('status', 'Izin diperbarui.');
    }

    public function destroy(Organization $organization, License $license): RedirectResponse
    {
        $license->delete();

        return back()->with('status', 'Izin dihapus.');
    }
}
