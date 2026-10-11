<?php

namespace App\Http\Controllers\Shared;

use App\Enums\OrganizationStatus;
use App\Enums\ProfileStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Profil menunggu verifikasi" (Admin dan OPD): kelompok yang profilnya diubah setelah diverifikasi.
 * Isi dibatasi Organization::visibleTo; kelompok yang ditutup tidak masuk antrean.
 */
class ProfileVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $organizations = Organization::visibleTo($request->user())
            ->where('profile_status', ProfileStatus::NeedsReview)
            ->where('status', '!=', OrganizationStatus::Closed)
            ->with(['village:id,name', 'institutionType:id,name'])
            ->orderBy('profile_changed_at')
            ->paginate(20);

        return view('shared.organizations.profile-queue', [
            'area' => $request->user()->role->area(),
            'organizations' => $organizations,
        ]);
    }
}
