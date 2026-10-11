<?php

namespace App\Support\Exports;

use App\Models\IssueFollowUp;
use App\Models\ReportIssue;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar masalah & kebutuhan untuk "Unduh Excel". Query yang diterima sudah dibatasi
 * ReportIssue::reviewableBy dan filter daftar, sehingga isinya sama dengan tabel di layar.
 */
class IssueList
{
    public const HEADER = [
        'Kelompok', 'Kampung', 'Periode laporan', 'Jenis', 'Kategori', 'Uraian',
        'Status tindak lanjut', 'OPD penanggung jawab', 'Catatan tindakan terakhir', 'Tanggal tindakan terakhir',
    ];

    public static function sheet(Builder $query): array
    {
        $issues = $query
            ->with([
                'monthlyReport:id,organization_id,period',
                'monthlyReport.organization:id,name,village_id',
                'monthlyReport.organization.village:id,name',
                'followUpOpd:id,name,short_name',
                'followUps' => fn ($query) => $query->select(['id', 'report_issue_id', 'note', 'acted_on']),
            ])
            ->orderByDesc(ReportIssue::periodSubquery())
            ->orderByDesc('report_issues.id')
            ->get();

        return [
            'name' => 'Masalah & Kebutuhan',
            'header' => self::HEADER,
            'rows' => $issues->map(function (ReportIssue $issue) {
                /** @var IssueFollowUp|null $last */
                $last = $issue->followUps->first();

                return [
                    $issue->monthlyReport->organization->name,
                    $issue->monthlyReport->organization->village?->name,
                    Format::month($issue->monthlyReport->period),
                    $issue->kind->label(),
                    $issue->category ? $issue->categoryLabel() : null,
                    $issue->description,
                    $issue->follow_up_status->label(),
                    $issue->followUpOpd ? ($issue->followUpOpd->short_name ?: $issue->followUpOpd->name) : null,
                    $last?->note,
                    $last ? Format::date($last->acted_on) : null,
                ];
            }),
        ];
    }
}
