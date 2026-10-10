<?php

namespace App\Policies;

use App\Enums\OrganizationStatus;
use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByOrganization;
use App\Support\Format;
use Illuminate\Auth\Access\Response;

class MonthlyReportPolicy
{
    use AuthorizesByOrganization;

    /** Daftar laporan untuk semua peran; isinya disaring lewat kelompok yang terlihat. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MonthlyReport $report): Response
    {
        return $this->organizationAccess($user, $report->organization_id);
    }

    public function create(User $user, Organization $organization): Response
    {
        $access = $this->organizationAccess($user, $organization->id);

        return match (true) {
            $access->denied() => $access,
            ! $user->isKelompok() => Response::deny('Laporan bulanan hanya diisi oleh kelompok.'),
            $organization->status === OrganizationStatus::Closed => Response::deny('Kelompok yang sudah tutup tidak membuat laporan baru.'),
            default => Response::allow(),
        };
    }

    public function update(User $user, MonthlyReport $report): Response
    {
        $access = $this->organizationAccess($user, $report->organization_id);

        return match (true) {
            $access->denied() => $access,
            ! $user->isKelompok() => Response::deny('Laporan bulanan hanya diubah oleh kelompok.'),
            ! $report->status->isEditable() => Response::deny('Laporan yang sudah diajukan atau terverifikasi tidak bisa diubah.'),
            default => Response::allow(),
        };
    }

    /** Laporan bulan berjalan baru bisa diajukan setelah bulannya selesai. */
    public function submit(User $user, MonthlyReport $report): Response
    {
        $access = $this->update($user, $report);

        return match (true) {
            $access->denied() => $access,
            ! $report->submissionIsOpen() => Response::deny('Laporan bulan ini baru bisa diajukan mulai '.Format::date($report->submissionOpensOn()).'.'),
            default => Response::allow(),
        };
    }

    /** Admin dan OPD pembina; laporan harus berstatus diajukan. */
    public function verify(User $user, MonthlyReport $report): Response
    {
        $access = $this->organizationAccess($user, $report->organization_id);

        return match (true) {
            $access->denied() => $access,
            $user->isKelompok() => Response::deny('Kelompok tidak bisa memverifikasi laporannya sendiri.'),
            $report->status !== ReportStatus::Submitted => Response::deny('Hanya laporan yang sudah diajukan yang bisa ditinjau.'),
            default => Response::allow(),
        };
    }

    public function requestRevision(User $user, MonthlyReport $report): Response
    {
        return $this->verify($user, $report);
    }

    public function delete(User $user, MonthlyReport $report): bool
    {
        return false;
    }
}
