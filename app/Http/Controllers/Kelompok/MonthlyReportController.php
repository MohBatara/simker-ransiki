<?php

namespace App\Http\Controllers\Kelompok;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use Illuminate\View\View;

class MonthlyReportController extends Controller
{
    public function show(MonthlyReport $report): View
    {
        return view('kelompok.reports.show', ['report' => $report->load('organization')]);
    }
}
