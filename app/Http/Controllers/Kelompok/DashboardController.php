<?php

namespace App\Http\Controllers\Kelompok;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Support\BannerImage;
use App\Support\Dashboards\KelompokSummary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $organization = Organization::findOrFail($request->user()->organization_id);
        $summary = new KelompokSummary($organization);

        return view('kelompok.dashboard', [
            'organization' => $organization,
            'due' => $summary->due(),
            'current' => $summary->current(),
            'revision' => $summary->latestRevision(),
            'checklist' => $summary->profileChecklist(),
            'history' => $summary->history(),
            'banner' => BannerImage::forOrganization($organization),
            'canCreate' => $request->user()->can('create', [MonthlyReport::class, $organization]),
        ]);
    }
}
