<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Reports\ReviewReport;
use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Models\Opd;
use App\Models\Organization;
use App\Support\Exports\ReportRecap;
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

    /**
     * Laporan › "Semua laporan": semua status, termasuk draf (isinya baru bisa dibuka setelah diajukan). Urutan:
     * menunggu verifikasi, perlu revisi, terverifikasi, draf (MonthlyReport::scopeReviewerOrder).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['q', 'status', 'period', 'opd', 'menunggu']);
        // "N menunggu verifikasi" = antrean yang sama dengan tab Perlu diverifikasi, dengan filter yang sedang aktif.
        $submittedCount = MonthlyReport::verificationQueue($user)->filter($filters)->count();

        $reports = MonthlyReport::visibleTo($user)
            ->filter($filters)
            ->withSubmissionInfo()
            ->with(['organization:id,name,village_id', 'organization.village:id,name', 'enterer:id,role'])
            ->reviewerOrder()
            ->paginate(20)
            ->withQueryString();

        return $this->list($request, 'all', $reports, $filters, $submittedCount);
    }

    /**
     * Antrean "Perlu diverifikasi": MonthlyReport::verificationQueue (semua laporan diajukan dalam cakupan), yang paling
     * lama menunggu di atas. Laporan yang ia isi/ajukan sendiri tetap tampil dan ditandai (pemisahan tugas).
     * Admin bisa menyaring ?hanya=admin: laporan yang tidak bisa diverifikasi petugas OPD mana pun.
     */
    public function queue(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['q', 'period', 'opd', 'menunggu', 'hanya']);
        $adminOnly = $user->isAdmin() && ($filters['hanya'] ?? null) === 'admin';
        if (! $adminOnly) {
            unset($filters['hanya']);
        }
        if (! $user->isAdmin()) {
            unset($filters['opd']);
        }

        $reports = MonthlyReport::verificationQueue($user)
            ->filter($filters)
            ->when($adminOnly, fn ($query) => $query->needsAdminVerification())
            ->withSubmissionInfo()
            ->when(MonthlyReport::separatesDuties(), fn ($query) => $query->withHandledBy($user))
            ->with(['organization:id,name,village_id', 'organization.village:id,name', 'enterer:id,role'])
            ->orderBy('last_submitted_at')
            ->orderBy('monthly_reports.id')
            ->paginate(20)
            ->withQueryString();

        return $this->list($request, 'queue', $reports, $filters, 0);
    }

    public function show(Request $request, MonthlyReport $report): View
    {
        $area = $request->user()->role->area();
        $report->loadDetail();
        [$back, $breadcrumbs] = $this->origin($request, $report, $area);

        return view('shared.reports.show', [
            'report' => $report,
            'attachments' => $report->attachments()->with('uploader')->latest()->get()->each->setRelation('attachable', $report),
            'back' => $back,
            'breadcrumbs' => $breadcrumbs,
            'editUrl' => $request->user()->can('update', $report) ? route($area.'.reports.edit', $report) : null,
            'area' => $area,
            'origin' => $this->originKey($request, $area),
        ]);
    }

    /**
     * Asal halaman (?dari=): menentukan tujuan "Kembali" dan remah. Nilai lain diabaikan.
     *
     * @return array{0: string, 1: list<array{label: string, href?: string}>}
     */
    private function origin(Request $request, MonthlyReport $report, string $area): array
    {
        $current = ['label' => 'Laporan '.Format::month($report->period)];

        return match ($this->originKey($request, $area)) {
            'kelompok' => [route($area.'.organizations.reports', $report->organization), [
                ['label' => $area === 'opd' ? 'Kelompok binaan' : 'Kelompok', 'href' => route($area.'.organizations.index')],
                ['label' => $report->organization->name, 'href' => route($area.'.organizations.show', $report->organization)],
                ['label' => 'Laporan', 'href' => route($area.'.organizations.reports', $report->organization)],
                $current,
            ]],
            'antrean' => [route($area.'.verifications.index'), [
                ['label' => 'Laporan', 'href' => route($area.'.reports.index')],
                ['label' => 'Perlu diverifikasi', 'href' => route($area.'.verifications.index')],
                $current,
            ]],
            'masalah' => [route($area.'.issues.index'), [
                ['label' => 'Laporan', 'href' => route($area.'.reports.index')],
                ['label' => 'Masalah & kebutuhan', 'href' => route($area.'.issues.index')],
                $current,
            ]],
            default => [route($area.'.reports.index'), [
                ['label' => 'Laporan', 'href' => route($area.'.reports.index')],
                $current,
            ]],
        };
    }

    private function originKey(Request $request, string $area): ?string
    {
        $origin = $request->query('dari');

        return in_array($origin, ['kelompok', 'antrean', 'masalah'], true) ? $origin : null;
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
        // Asal halaman ikut dibawa agar "Kembali" dan remah tetap sama setelah tindakan.
        $area = $request->user()->role->area();
        $show = route($area.'.reports.show', array_filter([$report, 'dari' => $this->originKey($request, $area)]));

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

    private function list(Request $request, string $mode, $reports, array $filters, int $submittedCount): View
    {
        $area = $request->user()->role->area();

        return view('shared.reports.index', [
            'area' => $area,
            'mode' => $mode,
            'reports' => $reports,
            'filters' => $filters,
            'submittedCount' => $submittedCount,
            'periods' => MonthlyReport::visibleTo($request->user())->distinct()->orderByDesc('period')->pluck('period')
                ->mapWithKeys(fn ($period) => [$period->format('Y-m') => Format::month($period)])->all(),
            'opds' => $area === 'admin' ? Opd::orderBy('name')->pluck('name', 'id')->all() : [],
            'recapPeriods' => $mode === 'all' ? ReportRecap::periodOptions($request->user()) : [],
        ]);
    }
}
