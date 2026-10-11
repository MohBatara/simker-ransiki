<?php

namespace App\Http\Controllers\Kelompok;

use App\Actions\Reports\SaveReportDraft;
use App\Actions\Reports\SubmitReport;
use App\Enums\OrganizationStatus;
use App\Enums\ReportingState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportDraftRequest;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Support\Format;
use App\Support\ReportForm;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Laporan bulanan milik kelompok yang login. Hak ubah/ajukan dicek Policy di route.
 */
class MonthlyReportController extends Controller
{
    public function index(Request $request): View
    {
        $organization = $this->organization($request);
        $reports = $organization->monthlyReports()->get()->keyBy(fn (MonthlyReport $report) => $report->period->format('Y-m'));

        // Kelompok tutup tidak lagi wajib lapor, jadi tidak ditandai terlambat.
        $closed = $organization->status === OrganizationStatus::Closed;

        $rows = $organization->reportingPeriods()->map(fn ($period) => [
            'period' => $period,
            'report' => $report = $reports->get($period->format('Y-m')),
            'deadline' => MonthlyReport::deadlineFor($period),
            'overdue' => ! $closed && ReportingState::for($report, $period) === ReportingState::Overdue,
        ]);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginated = new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, [
            'path' => $request->url(),
        ]);

        return view('kelompok.reports.index', [
            'organization' => $organization,
            'rows' => $paginated,
            'canCreate' => $request->user()->can('create', [MonthlyReport::class, $organization]),
        ]);
    }

    /** Membuat draf kosong agar simpan otomatis dan unggah dokumentasi punya ID laporan. */
    public function store(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        Gate::authorize('create', [MonthlyReport::class, $organization]);

        $request->validate(['period' => ['required', 'date_format:Y-m']], [
            'period.required' => 'Pilih bulan laporan.',
            'period.date_format' => 'Bulan laporan tidak valid.',
        ]);
        $period = Carbon::createFromFormat('Y-m-d', $request->input('period').'-01')->startOfDay();

        if (! $organization->acceptsReportFor($period)) {
            throw ValidationException::withMessages(['period' => 'Laporan hanya bisa dibuat untuk bulan sejak kewajiban lapor dimulai sampai bulan ini.']);
        }

        $existing = $organization->monthlyReports()->whereDate('period', $period)->first();

        if (! $existing) {
            try {
                $report = $organization->monthlyReports()->create(['period' => $period]);

                return redirect()->route('kelompok.reports.edit', $report);
            } catch (UniqueConstraintViolationException) {
                // Dua klik bersamaan: laporan sudah dibuat oleh permintaan lain.
                $existing = $organization->monthlyReports()->whereDate('period', $period)->firstOrFail();
            }
        }

        return redirect()->to($this->reportUrl($request, $existing))
            ->with('status', 'Bulan ini sudah ada laporan. Laporan yang ada dibuka.');
    }

    public function show(Request $request, MonthlyReport $report): View
    {
        return view('shared.reports.show', [
            'report' => $report->loadDetail(),
            'attachments' => $report->attachments()->with('uploader')->latest()->get()->each->setRelation('attachable', $report),
            'back' => route('kelompok.reports.index'),
            'breadcrumbs' => [
                ['label' => 'Laporan Bulanan', 'href' => route('kelompok.reports.index')],
                ['label' => 'Laporan '.Format::month($report->period)],
            ],
            'editUrl' => $request->user()->can('update', $report) ? route('kelompok.reports.edit', $report) : null,
        ]);
    }

    public function edit(Request $request, MonthlyReport $report): View
    {
        $report->load(['organization', 'items', 'issues', 'latestRevisionRequest.user.opd']);
        $submission = Gate::inspect('submit', $report);

        return view('kelompok.reports.form', [
            'report' => $report,
            'state' => ReportForm::state($report),
            'previous' => ReportForm::previous($report),
            'products' => $report->organization->products()->orderBy('name')->get(['id', 'name', 'unit']),
            'attachments' => $report->attachments()->with('uploader')->latest()->get()->each->setRelation('attachable', $report),
            'canSubmit' => $submission->allowed(),
            'submitNote' => $submission->allowed() ? null : $submission->message(),
        ]);
    }

    /** Simpan otomatis dan tombol "Simpan" (JSON). */
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
        try {
            $submit->handle($report, $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('kelompok.reports.edit', $report)
                ->withErrors($exception->errors())
                ->with('error', 'Laporan belum bisa diajukan. Lengkapi isian yang ditandai.');
        }

        return redirect()->route('kelompok.reports.show', $report)->with('status', 'Laporan diajukan.');
    }

    /** Draf dan revisi dibuka di form; laporan terkunci dibuka di halaman detail. */
    private function reportUrl(Request $request, MonthlyReport $report): string
    {
        return $request->user()->can('update', $report)
            ? route('kelompok.reports.edit', $report)
            : route('kelompok.reports.show', $report);
    }

    private function organization(Request $request): Organization
    {
        return Organization::findOrFail($request->user()->organization_id);
    }
}
