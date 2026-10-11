<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Support\Exports\Pdf;
use App\Support\Exports\ReportRecap;
use App\Support\Exports\Spreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unduhan laporan: rekap per periode (Admin/OPD, Excel atau PDF) dan PDF satu laporan (semua peran yang
 * boleh melihat laporannya; dicek Policy `view` di route).
 */
class ReportExportController extends Controller
{
    /** ?periode=Y-m (nilai tidak sah kembali ke bulan wajib lapor) dan ?format=xlsx|pdf. */
    public function recap(Request $request): Response
    {
        $user = $request->user();
        $recap = new ReportRecap($user, ReportRecap::resolvePeriod($user, $request->query('periode')));

        if ($request->query('format') === 'pdf') {
            return Pdf::download('shared.exports.recap', [
                'recap' => $recap,
                'scope' => $user->isOpd() ? 'Kelompok binaan '.($user->opd?->name ?? 'OPD') : 'Seluruh kelompok Distrik Ransiki',
            ], $recap->filenameBase().'.pdf', $user);
        }

        return Spreadsheet::download($recap->filenameBase().'.xlsx', $recap->sheets(Pdf::printedLine($user)));
    }

    public function report(Request $request, MonthlyReport $report): Response
    {
        $report->loadDetail()->loadMissing('organization.village');

        return Pdf::download('shared.exports.report', [
            'report' => $report,
            'attachments' => $report->attachments()->oldest()->get(),
        ], 'laporan-'.Str::limit($report->organization->slug, 60, '').'-'.$report->period->format('Y-m').'.pdf', $request->user());
    }
}
