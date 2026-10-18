<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IssueKind;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ReportIssue;
use App\Support\Dashboards\DistrictStats;
use App\Support\Dashboards\ReportingPeriod;
use App\Support\Dashboards\ReportingStats;
use App\Support\Dashboards\Statistics;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DistrictStats $district): View
    {
        $earliest = Organization::min('reporting_starts_on');
        $earliest = $earliest ? CarbonImmutable::parse($earliest)->startOfMonth() : null;
        $period = ReportingPeriod::resolve($request->query('periode'), $earliest);
        $stats = new ReportingStats($request->user(), $period);

        return view('admin.dashboard', [
            'period' => $period,
            'periods' => ReportingPeriod::options($earliest),
            'attention' => $district->attention($request->user()),
            'progress' => $stats->progress(),
            'perOpd' => $stats->progressPerOpd(),
            'notSubmitted' => $stats->notSubmitted(),
            'overdue' => $stats->overdue(),
            'staleNeeds' => ReportIssue::reviewableBy($request->user())->where('kind', IssueKind::Support)->stale(config('simker.follow_up.stale_days'))->count(),
            'organizations' => $district->organizations(),
            'economy' => $district->economy($period),
            'production' => $district->production($period),
            'overview' => Statistics::overview($request->user(), $earliest),
            // Sama dengan badge menu Kelompok (dihitung sekali per request).
            'profileQueue' => Navigation::counts($request->user())['profiles'],
        ]);
    }
}
