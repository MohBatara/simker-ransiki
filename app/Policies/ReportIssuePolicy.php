<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Models\ReportIssue;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByOrganization;
use Illuminate\Auth\Access\Response;

/**
 * Masalah & kebutuhan dari laporan bulanan. Peninjau (Admin, OPD pembina) hanya melihat isian laporan yang
 * diajukan atau terverifikasi (sejalan dengan ReportIssue::reviewableBy); kelompok melihat miliknya.
 */
class ReportIssuePolicy
{
    use AuthorizesByOrganization;

    public function view(User $user, ReportIssue $issue): Response
    {
        $report = $issue->monthlyReport;
        $access = $this->organizationAccess($user, $report->organization_id);

        return match (true) {
            $access->denied() => $access,
            ! $user->isKelompok() && ! in_array($report->status, [ReportStatus::Submitted, ReportStatus::Verified], true) => Response::denyAsNotFound(),
            default => Response::allow(),
        };
    }

    /** Ubah status tindak lanjut: Admin dan OPD pembina. Kelompok hanya bisa melihat. */
    public function followUp(User $user, ReportIssue $issue): Response
    {
        $access = $this->view($user, $issue);

        return match (true) {
            $access->denied() => $access,
            $user->isKelompok() => Response::deny('Status tindak lanjut diubah oleh OPD atau Admin distrik.'),
            default => Response::allow(),
        };
    }
}
