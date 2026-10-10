<?php

namespace App\Http\Controllers\Shared;

use App\Enums\IssueKind;
use App\Enums\ProblemCategory;
use App\Enums\SupportCategory;
use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\ReportIssue;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Masalah & Kebutuhan": isian bagian E dan F laporan yang sudah diajukan. OPD melihat kelompok
 * binaannya, Admin seluruh distrik.
 */
class ReportIssueController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $area = $user->role->area();
        $filters = $request->only(['kind', 'category', 'period', 'opd']);

        $issues = ReportIssue::reviewableBy($user)
            ->filter($filters)
            ->with('monthlyReport:id,organization_id,period', 'monthlyReport.organization:id,name')
            ->orderByDesc(MonthlyReport::select('period')->whereColumn('monthly_reports.id', 'report_issues.monthly_report_id'))
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
            'categories' => collect($categories)->sort()->all(),
            'periods' => MonthlyReport::visibleTo($user)->distinct()->orderByDesc('period')->pluck('period')
                ->mapWithKeys(fn ($period) => [$period->format('Y-m') => Format::month($period)])->all(),
            'opds' => $area === 'admin' ? Opd::orderBy('name')->pluck('name', 'id')->all() : [],
        ]);
    }
}
