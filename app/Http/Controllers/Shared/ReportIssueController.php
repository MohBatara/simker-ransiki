<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Issues\FollowUpIssue;
use App\Enums\IssueKind;
use App\Enums\ProblemCategory;
use App\Enums\SupportCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\IssueFollowUpRequest;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\ReportIssue;
use App\Models\Village;
use App\Support\Exports\IssueList;
use App\Support\Exports\Spreadsheet;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Masalah & Kebutuhan": isian bagian E dan F laporan yang sudah diajukan, beserta tindak lanjutnya.
 * OPD melihat kelompok binaannya, Admin seluruh distrik (ReportIssue::reviewableBy + ReportIssuePolicy).
 */
class ReportIssueController extends Controller
{
    public const FILTERS = ['kind', 'category', 'status', 'village', 'period', 'opd', 'lama'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $area = $user->role->area();
        $filters = $request->only(self::FILTERS);

        $query = ReportIssue::reviewableBy($user)->filter($filters);
        $kindCounts = (clone $query)->toBase()->selectRaw('kind, COUNT(*) as total')->groupBy('kind')->pluck('total', 'kind');

        $issues = $query
            ->with(['monthlyReport:id,organization_id,period', 'monthlyReport.organization:id,name,village_id', 'monthlyReport.organization.village:id,name'])
            ->orderByDesc(ReportIssue::periodSubquery())
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $categories = match (IssueKind::tryFrom((string) ($filters['kind'] ?? ''))) {
            IssueKind::Problem => ProblemCategory::options(),
            IssueKind::Support => SupportCategory::options(),
            null => ProblemCategory::options() + SupportCategory::options(),
        };

        return view('shared.reports.issues', [
            'area' => $area,
            'issues' => $issues,
            'filters' => $filters,
            'kindCounts' => ['problem' => (int) ($kindCounts['problem'] ?? 0), 'support' => (int) ($kindCounts['support'] ?? 0)],
            'categories' => collect($categories)->sort()->all(),
            'villages' => Village::orderBy('name')->pluck('name', 'id')->all(),
            'periods' => MonthlyReport::visibleTo($user)->distinct()->orderByDesc('period')->pluck('period')
                ->mapWithKeys(fn ($period) => [$period->format('Y-m') => Format::month($period)])->all(),
            'opds' => $area === 'admin' ? Opd::orderBy('name')->pluck('name', 'id')->all() : [],
        ]);
    }

    /** "Unduh Excel": isi sama dengan daftar di layar (reviewableBy + filter aktif), tanpa paginasi. */
    public function export(Request $request): BinaryFileResponse
    {
        $query = ReportIssue::reviewableBy($request->user())->filter($request->only(self::FILTERS));

        return Spreadsheet::download('masalah-kebutuhan-'.now()->format('Y-m-d').'.xlsx', [IssueList::sheet($query)]);
    }

    /** Detail satu masalah/kebutuhan: isi, riwayat tindak lanjut, dan form ubah status. */
    public function show(Request $request, ReportIssue $issue): View
    {
        $area = $request->user()->role->area();
        $issue->load(['monthlyReport.organization.village', 'monthlyReport.organization.opds:id,name,short_name', 'followUps.user.opd', 'followUps.opd', 'followUpOpd']);

        return view('shared.reports.issue', [
            'area' => $area,
            'issue' => $issue,
            'report' => $issue->monthlyReport,
            'canFollowUp' => $request->user()->can('followUp', $issue),
            // Admin memilih OPD penanggung jawab; OPD pembina kelompok ini didahulukan.
            'opds' => $area === 'admin'
                ? $issue->monthlyReport->organization->opds->pluck('name', 'id')->all() + Opd::orderBy('name')->pluck('name', 'id')->all()
                : [],
            'back' => route($area.'.issues.index'),
        ]);
    }

    public function followUp(IssueFollowUpRequest $request, ReportIssue $issue, FollowUpIssue $followUp): RedirectResponse
    {
        $previous = $issue->follow_up_status;
        $issue = $followUp->handle($request->user(), $issue, $request->status(), $request->validated('note'), $request->actedOn(), $request->validated('opd_id'));

        return redirect()->route($request->user()->role->area().'.issues.show', $issue)->with('status', $issue->follow_up_status === $previous
            ? 'Catatan tindakan disimpan.'
            : 'Status diubah menjadi '.mb_strtolower($issue->follow_up_status->label()).'. Kelompok mendapat notifikasi.');
    }
}
