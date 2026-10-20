<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Dashboards\ReportingPeriod;
use App\Support\Dashboards\Statistics;
use App\Support\Exports\LegalRecap;
use App\Support\Exports\Pdf;
use App\Support\Exports\Spreadsheet;
use App\Support\OrganizationOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Halaman Statistik Admin dan OPD: tab Ekonomi dan Legalitas. Semua angka dari App\Support\Dashboards\Statistics,
 * yang membatasi kelompok dengan Organization::visibleTo (filter OPD hanya berlaku untuk Admin).
 */
class StatisticsController extends Controller
{
    public function economy(Request $request): View
    {
        [$stats, $earliest] = $this->statistics($request);

        return view('shared.statistics.economy', [
            ...$this->shared($request, $stats),
            'months' => ReportingPeriod::options($earliest),
            'summary' => $stats->summary(),
            'incomeTrend' => $stats->incomeTrend(),
            // Penjualan bulan terakhir rentang dibanding bulan sebelumnya, setara (kelompok yang sama, filter ikut).
            'salesChange' => $stats->matchedChange($stats->to())['income_sales'],
            'compliance' => $stats->compliance(),
            'sectors' => $stats->incomeBySector(),
            // Lengkap: tab Ekonomi menampilkan 15 baris lalu "Tampilkan semua".
            'production' => $stats->production(PHP_INT_MAX),
            'issues' => $stats->issues(),
        ]);
    }

    public function legal(Request $request): View
    {
        [$stats] = $this->statistics($request);

        return view('shared.statistics.legal', [
            ...$this->shared($request, $stats),
            'months' => null,
            'legal' => $stats->legalSummary(),
            'byType' => $stats->legalBreakdown('type'),
            'byVillage' => $stats->legalBreakdown('village'),
            'needsFix' => $stats->licensesNeedingFix(),
            'without' => $stats->withoutLicense(),
            'expiring' => $stats->expiringLicenses(),
        ]);
    }

    /** "Unduh Excel" rekap legalitas, dengan filter yang sama dengan layar. */
    public function legalExport(Request $request): BinaryFileResponse
    {
        [$stats] = $this->statistics($request);

        return Spreadsheet::download('rekap-legalitas-'.now()->format('Y-m-d').'.xlsx', LegalRecap::sheets($stats, Pdf::printedLine($request->user())));
    }

    /** @return array{0: Statistics, 1: ?CarbonImmutable} */
    private function statistics(Request $request): array
    {
        $user = $request->user();
        $earliest = Organization::visibleTo($user)->min('reporting_starts_on');
        $earliest = $earliest ? CarbonImmutable::parse($earliest)->startOfMonth() : null;
        [$from, $to] = Statistics::resolveRange($request->query('dari'), $request->query('sampai'), $earliest);

        return [new Statistics($user, $request->only(Statistics::FILTERS), $from, $to), $earliest];
    }

    private function shared(Request $request, Statistics $stats): array
    {
        return [
            'area' => $request->user()->role->area(),
            'stats' => $stats,
            'filters' => $stats->filters(),
            'options' => OrganizationOptions::all(),
        ];
    }
}
