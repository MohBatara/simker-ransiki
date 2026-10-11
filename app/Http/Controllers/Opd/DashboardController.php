<?php

namespace App\Http\Controllers\Opd;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Support\BannerImage;
use App\Support\Dashboards\DistrictStats;
use App\Support\Dashboards\ReportingPeriod;
use App\Support\Dashboards\ReportingStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('opd');
        $earliest = Organization::visibleTo($user)->min('reporting_starts_on');
        $earliest = $earliest ? CarbonImmutable::parse($earliest)->startOfMonth() : null;
        $period = ReportingPeriod::resolve($request->query('periode'), $earliest);
        $stats = new ReportingStats($user, $period);
        $waiting = MonthlyReport::visibleTo($user)->where('monthly_reports.status', ReportStatus::Submitted);

        return view('opd.dashboard', [
            'user' => $user,
            'banner' => BannerImage::cover(),
            'period' => $period,
            'periods' => ReportingPeriod::options($earliest),
            'waitingCount' => (clone $waiting)->count(),
            'waitingLong' => MonthlyReport::visibleTo($user)->waitingLongerThan(DistrictStats::WAITING_DAYS)->count(),
            'oldestWaiting' => (clone $waiting)->withSubmissionInfo()->with('organization:id,name')->orderBy('last_submitted_at')->first(),
            'progress' => $stats->progress(),
            'notSubmitted' => $stats->notSubmitted(),
            'topIssues' => $stats->topIssues(),
            'trend' => $stats->verifiedTrend(),
        ]);
    }
}
