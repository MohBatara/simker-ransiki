<?php

namespace App\Http\Controllers\Opd;

use App\Enums\IssueKind;
use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
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
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('opd');
        $earliest = Organization::visibleTo($user)->min('reporting_starts_on');
        $earliest = $earliest ? CarbonImmutable::parse($earliest)->startOfMonth() : null;
        $period = ReportingPeriod::resolve($request->query('periode'), $earliest);
        $stats = new ReportingStats($user, $period);
        // Satu definisi dengan tab Perlu diverifikasi dan badge menu Laporan.
        $waiting = MonthlyReport::verificationQueue($user);

        return view('opd.dashboard', [
            'user' => $user,
            'period' => $period,
            'periods' => ReportingPeriod::options($earliest),
            'waitingCount' => (clone $waiting)->count(),
            'waitingLong' => MonthlyReport::verificationQueue($user)->waitingLongerThan(DistrictStats::WAITING_DAYS)->count(),
            'oldestWaiting' => (clone $waiting)->withSubmissionInfo()->with('organization:id,name')->orderBy('last_submitted_at')->first(),
            'progress' => $stats->progress(),
            'notSubmitted' => $stats->notSubmitted(),
            'overdue' => $stats->overdue(),
            'staleNeeds' => ReportIssue::reviewableBy($user)->where('kind', IssueKind::Support)->stale(config('simker.follow_up.stale_days'))->count(),
            'topIssues' => $stats->topIssues(),
            'economyTrend' => Statistics::recentEconomy($user, $period, $earliest),
            'economyChange' => (new Statistics($user, [], $period, $period))->matchedChange($period),
            'latestReports' => (clone $waiting)->withSubmissionInfo()->with('organization:id,name')->orderByDesc('last_submitted_at')->limit(5)->get(),
            // Produksi per produk periode terpilih, kelompok binaan saja (Statistics memakai visibleTo).
            'production' => (new Statistics($user, [], $period, $period))->production(5),
            'profileQueue' => Navigation::counts($user)['profiles'],
        ]);
    }
}
