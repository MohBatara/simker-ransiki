<?php

namespace App\Http\Controllers\Shared;

use App\Enums\ProfileStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tab "Verifikasi profil" (Admin dan OPD): Organization::profileReviewQueue, dipilah dengan chip ?jenis=:
 * "perubahan" (Perubahan menunggu verifikasi, paling lama diubah di atas) atau "baru" (Profil belum pernah diverifikasi,
 * urut nama). Bawaan: perubahan bila ada, selain itu baru; nilai lain diabaikan.
 */
class ProfileVerificationController extends Controller
{
    public const KINDS = ['perubahan' => ProfileStatus::NeedsReview, 'baru' => ProfileStatus::Unverified];

    public function index(Request $request): View
    {
        $counts = Navigation::counts($request->user());
        $kind = $request->query('jenis');
        $kind = is_string($kind) && array_key_exists($kind, self::KINDS) ? $kind : ($counts['profiles'] > 0 ? 'perubahan' : 'baru');

        $organizations = Organization::profileReviewQueue($request->user())
            ->where('profile_status', self::KINDS[$kind])
            ->with(['village:id,name', 'institutionType:id,name'])
            ->when($kind === 'perubahan', fn ($query) => $query->orderBy('profile_changed_at'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('shared.organizations.profile-queue', [
            'area' => $request->user()->role->area(),
            'organizations' => $organizations,
            'kind' => $kind,
            'kindCounts' => ['perubahan' => $counts['profiles'], 'baru' => $counts['profiles_new']],
        ]);
    }
}
