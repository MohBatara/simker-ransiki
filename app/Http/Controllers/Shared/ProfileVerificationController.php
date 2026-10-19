<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tab "Verifikasi profil" (Admin dan OPD): Organization::profileReviewQueue. Perubahan menunggu verifikasi lebih dulu
 * (paling lama diubah di atas), lalu profil yang belum pernah diverifikasi (urut nama).
 */
class ProfileVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $organizations = Organization::profileReviewQueue($request->user())
            ->with(['village:id,name', 'institutionType:id,name'])
            ->orderByRaw('profile_status = ? desc', [\App\Enums\ProfileStatus::NeedsReview->value])
            ->orderBy('profile_changed_at')
            ->orderBy('name')
            ->paginate(20);

        return view('shared.organizations.profile-queue', [
            'area' => $request->user()->role->area(),
            'organizations' => $organizations,
        ]);
    }
}
