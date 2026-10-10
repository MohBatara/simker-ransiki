<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Reports\ReviewReport;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Laporan bulanan untuk peninjau (Admin seluruh distrik, OPD kelompok binaan). Hak lihat dan
 * tindakan sepenuhnya ditentukan MonthlyReportPolicy.
 */
class MonthlyReportController extends Controller
{
    private const NOTE_RULES = ['required', 'string', 'min:10', 'max:2000'];

    /** "Semua Laporan": semua status, termasuk draf (isinya baru bisa dibuka setelah diajukan). */
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'status', 'period', 'opd']);

        $reports = MonthlyReport::visibleTo($request->user())
            ->filter($filters)
            ->with('organization:id,name')
            ->orderByDesc('period')
            ->orderBy(Organization::select('name')->whereColumn('organizations.id', 'monthly_reports.organization_id'))
            ->paginate(20)
            ->withQueryString();

        return $this->list($request, 'all', $reports, $filters);
    }

    /** Antrean verifikasi OPD: laporan diajukan, yang paling lama menunggu di atas. */
    public function queue(Request $request): View
    {
        $filters = $request->only(['q', 'period']);

        $reports = MonthlyReport::visibleTo($request->user())
            ->where('monthly_reports.status', ReportStatus::Submitted)
            ->filter($filters)
            ->withSubmissionInfo()
            ->with('organization:id,name')
            ->orderBy('last_submitted_at')
            ->orderBy('monthly_reports.id')
            ->paginate(20)
            ->withQueryString();

        return $this->list($request, 'queue', $reports, $filters);
    }

    public function show(Request $request, MonthlyReport $report): View
    {
        $area = $request->user()->role->area();

        return view('shared.reports.show', [
            'report' => $report->loadDetail(),
            'attachments' => $report->attachments()->with('uploader')->latest()->get()->each->setRelation('attachable', $report),
            'back' => $report->status === ReportStatus::Submitted && $area === 'opd' ? route('opd.verifications.index') : route($area.'.reports.index'),
            'editUrl' => null,
            'area' => $area,
        ]);
    }

    public function verify(Request $request, MonthlyReport $report, ReviewReport $review): RedirectResponse
    {
        $data = $request->validate(['verify_note' => ['nullable', 'string', 'max:2000']], [
            'verify_note.max' => 'Catatan paling panjang 2.000 karakter.',
        ]);

        return $this->act($request, $report, fn () => $review->verify($report, $request->user(), $data['verify_note'] ?? null), 'Laporan diverifikasi.');
    }

    public function requestRevision(Request $request, MonthlyReport $report, ReviewReport $review): RedirectResponse
    {
        $data = $request->validate(['revision_note' => self::NOTE_RULES], $this->noteMessages('revision_note', 'catatan revisi'));

        return $this->act($request, $report, fn () => $review->requestRevision($report, $request->user(), $data['revision_note']), 'Revisi diminta. Kelompok mendapat notifikasi.');
    }

    public function reopen(Request $request, MonthlyReport $report, ReviewReport $review): RedirectResponse
    {
        $data = $request->validate(['reopen_note' => self::NOTE_RULES], $this->noteMessages('reopen_note', 'alasan'));

        return $this->act($request, $report, fn () => $review->reopen($report, $request->user(), $data['reopen_note']), 'Laporan dibuka kembali untuk revisi.');
    }

    /** Bila peninjau lain sudah bertindak lebih dulu, kembali ke detail dengan penjelasan. */
    private function act(Request $request, MonthlyReport $report, callable $action, string $message): RedirectResponse
    {
        $show = route($request->user()->role->area().'.reports.show', $report);

        try {
            $action();
        } catch (ValidationException $exception) {
            return redirect()->to($show)->with('error', collect($exception->errors())->flatten()->first());
        }

        return redirect()->to($show)->with('status', $message);
    }

    private function noteMessages(string $field, string $name): array
    {
        return [
            "{$field}.required" => "Isi {$name} agar kelompok tahu apa yang perlu diperbaiki.",
            "{$field}.min" => ucfirst($name).' terlalu singkat. Tulis paling sedikit 10 karakter.',
            "{$field}.max" => ucfirst($name).' paling panjang 2.000 karakter.',
        ];
    }

    private function list(Request $request, string $mode, $reports, array $filters): View
    {
        $area = $request->user()->role->area();

        return view('shared.reports.index', [
            'area' => $area,
            'mode' => $mode,
            'reports' => $reports,
            'filters' => $filters,
            'periods' => MonthlyReport::visibleTo($request->user())->distinct()->orderByDesc('period')->pluck('period')
                ->mapWithKeys(fn ($period) => [$period->format('Y-m') => Format::month($period)])->all(),
            'opds' => $area === 'admin' ? Opd::orderBy('name')->pluck('name', 'id')->all() : [],
        ]);
    }
}
