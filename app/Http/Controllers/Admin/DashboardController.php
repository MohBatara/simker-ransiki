<?php

namespace App\Http\Controllers\Admin;

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
            // Sama dengan OPD dan tab Ekonomi: urut jumlah kelompok, nama kelompok, pembanding setara bulan lalu.
            'production' => (new Statistics($request->user(), [], $period, $period))->production(5),
            // Kartu Ekonomi: penjualan dan biaya 6 bulan sampai periode terpilih + persen perubahan (hanya terverifikasi).
            'economyTrend' => Statistics::recentEconomy($request->user(), $period, $earliest),
            // Persen perubahan setara: hanya kelompok yang terverifikasi di periode ini dan bulan sebelumnya.
            'economyChange' => (new Statistics($request->user(), [], $period, $period))->matchedChange($period),
            // Kartu Laporan terbaru: 5 pengajuan terakhir dari antrean verifikasi yang sama dengan tab Perlu diverifikasi.
            'latestReports' => MonthlyReport::verificationQueue($request->user())->withSubmissionInfo()->with('organization:id,name')->orderByDesc('last_submitted_at')->limit(5)->get(),
            // Sama dengan badge menu Kelompok (dihitung sekali per request).
            // Sama dengan badge menu Kelompok (perubahan menunggu) dan profil yang belum pernah diverifikasi.
            'profileQueue' => Navigation::counts($request->user())['profiles'],
            'profilesNew' => Navigation::counts($request->user())['profiles_new'],
        ]);
    }
}
