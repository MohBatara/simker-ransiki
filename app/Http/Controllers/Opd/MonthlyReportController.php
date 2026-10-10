<?php

namespace App\Http\Controllers\Opd;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use Illuminate\View\View;

class MonthlyReportController extends Controller
{
    public function show(MonthlyReport $report): View
    {
        return view('shared.reports.show', [
            'report' => $report->loadDetail(),
            'attachments' => $report->attachments()->with('uploader')->latest()->get()->each->setRelation('attachable', $report),
            'back' => route('opd.organizations.reports', $report->organization_id),
            'editUrl' => null,
        ]);
    }
}
