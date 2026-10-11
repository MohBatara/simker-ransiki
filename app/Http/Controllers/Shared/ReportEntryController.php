<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Reports\CreateReport;
use App\Actions\Reports\SaveReportDraft;
use App\Actions\Reports\SubmitReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportDraftRequest;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Support\ReportForm;
use App\Support\ReportPeriodInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Isi laporan atas nama kelompok" untuk OPD pembina dan Admin (kelompok tanpa HP/akun). Form dan aturan
 * isiannya sama dengan milik kelompok; hak dicek MonthlyReportPolicy. Pengisi tercatat di report_reviews
 * dan, dengan pemisahan tugas, tidak boleh memverifikasi laporan yang sama.
 */
class ReportEntryController extends Controller
{
    public function store(Request $request, Organization $organization, CreateReport $create): RedirectResponse
    {
        $area = $request->user()->role->area();
        Gate::authorize('create', [MonthlyReport::class, $organization]);

        [$report, $created] = $create->handle($request->user(), $organization, ReportPeriodInput::from($request));

        if ($created) {
            return redirect()->route($area.'.reports.edit', $report);
        }
        if ($request->user()->can('update', $report)) {
            return redirect()->route($area.'.reports.edit', $report)->with('status', 'Bulan ini sudah ada laporan. Laporan yang ada dibuka.');
        }

        // Draf yang sedang diisi kelompok sendiri, atau laporan yang sudah dikunci.
        return $request->user()->can('view', $report)
            ? redirect()->route($area.'.reports.show', $report)->with('status', 'Bulan ini sudah ada laporan. Laporan yang ada dibuka.')
            : back()->with('error', 'Kelompok sedang mengisi draf laporan bulan ini dari akunnya. Minta kelompok menyelesaikan dan mengajukannya.');
    }

    public function edit(Request $request, MonthlyReport $report): View
    {
        $area = $request->user()->role->area();

        return view('kelompok.reports.form', ReportForm::page($report, $request->user(), [
            'save' => route($area.'.reports.draft', $report),
            'submit' => route($area.'.reports.submit', $report),
            'back' => route($area.'.organizations.reports', $report->organization_id),
            'products' => route($area.'.organizations.products', $report->organization_id),
        ]));
    }

    public function saveDraft(ReportDraftRequest $request, MonthlyReport $report, SaveReportDraft $save): JsonResponse
    {
        $report = $save->handle($report, $request->user(), $request->validated(), $request->integer('version') ?: null);

        return response()->json([
            'version' => $report->updated_at->getTimestamp(),
            'profit' => $report->profit,
            'saved_at' => $report->updated_at->format('H.i'),
        ]);
    }

    public function submit(Request $request, MonthlyReport $report, SubmitReport $submit): RedirectResponse
    {
        $area = $request->user()->role->area();

        try {
            $submit->handle($report, $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route($area.'.reports.edit', $report)
                ->withErrors($exception->errors())
                ->with('error', 'Laporan belum bisa diajukan. Lengkapi isian yang ditandai.');
        }

        $message = MonthlyReport::separatesDuties()
            ? 'Laporan diajukan. Perlu diverifikasi oleh Admin atau petugas OPD lain.'
            : 'Laporan diajukan.';

        return redirect()->route($area.'.reports.show', [$report, 'dari' => 'kelompok'])->with('status', $message);
    }
}
