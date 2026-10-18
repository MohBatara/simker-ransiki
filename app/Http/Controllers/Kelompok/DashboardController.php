<?php

namespace App\Http\Controllers\Kelompok;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Support\Dashboards\KelompokSummary;
use App\Support\Dashboards\Statistics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $organization = Organization::findOrFail($request->user()->organization_id);
        $summary = new KelompokSummary($organization);
        // visibleTo untuk akun kelompok = kelompoknya sendiri; 12 bulan sampai bulan wajib lapor.
        $stats = new Statistics($request->user());

        return view('kelompok.dashboard', [
            'organization' => $organization,
            'due' => $summary->due(),
            'current' => $summary->current(),
            'revision' => $summary->latestRevision(),
            'checklist' => $summary->profileChecklist(),
            'history' => $summary->history(),
            'followUps' => $summary->followUps(),
            'incomeTrend' => $stats->incomeTrend(),
            'productionTrend' => $stats->productionTrend(),
            'months' => $stats->months(),
            'canCreate' => $request->user()->can('create', [MonthlyReport::class, $organization]),
        ]);
    }
}
