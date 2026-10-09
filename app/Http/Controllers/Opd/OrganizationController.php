<?php

namespace App\Http\Controllers\Opd;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function show(Organization $organization): View
    {
        return view('opd.organizations.show', [
            'organization' => $organization->load('institutionType', 'village'),
        ]);
    }

    public function edit(Organization $organization): View
    {
        return view('opd.organizations.edit', ['organization' => $organization]);
    }
}
