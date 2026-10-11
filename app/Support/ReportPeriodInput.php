<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Bulan laporan dari isian "period" (Y-m) pada form "Buat laporan" / "Isi laporan". */
class ReportPeriodInput
{
    public static function from(Request $request): CarbonImmutable
    {
        $request->validate(['period' => ['required', 'date_format:Y-m']], [
            'period.required' => 'Pilih bulan laporan.',
            'period.date_format' => 'Bulan laporan tidak valid.',
        ]);

        return CarbonImmutable::createFromFormat('!Y-m', $request->input('period'));
    }
}
