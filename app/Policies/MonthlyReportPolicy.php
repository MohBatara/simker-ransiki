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

    public const HANDLED_MESSAGE = 'Anda ikut mengisi atau mengajukan laporan ini. Perlu diverifikasi oleh Admin atau petugas OPD lain.';

    /** Daftar laporan untuk semua peran; isinya disaring lewat kelompok yang terlihat. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Admin dan OPD baru bisa membuka isi laporan setelah pernah diajukan; draf kelompok yang belum pernah
     * diajukan hanya tampil sebagai baris status di daftar. Pengecualian: draf yang dibuat OPD/Admin atas
     * nama kelompok.
     */
    public function view(User $user, MonthlyReport $report): Response
    {
        $access = $this->organizationAccess($user, $report->organization_id);

        return match (true) {
            $access->denied() => $access,
            ! $user->isKelompok() && $report->submitted_at === null && ! $report->isEnteredOnBehalf() => Response::denyAsNotFound(),
            default => Response::allow(),
        };
    }

    /** Kelompok sendiri, atau OPD pembina/Admin yang mengisi atas nama kelompok (mis. kelompok tanpa HP/akun). */
    public function create(User $user, Organization $organization): Response
    {
        $access = $this->organizationAccess($user, $organization->id);

        return match (true) {
            $access->denied() => $access,
            $organization->status === OrganizationStatus::Closed => Response::deny('Kelompok yang sudah tutup tidak membuat laporan baru.'),
            default => Response::allow(),
        };
    }

    public function update(User $user, MonthlyReport $report): Response
    {
        $access = $this->view($user, $report);

        return match (true) {
            $access->denied() => $access,
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

    /**
     * Admin dan OPD pembina; laporan harus berstatus diajukan. Dengan pemisahan tugas, akun yang mengisi atau
     * mengajukan laporan ini tidak boleh meninjaunya (verifikasi maupun minta revisi).
     */
    public function verify(User $user, MonthlyReport $report): Response
    {
        $access = $this->organizationAccess($user, $report->organization_id);

        return match (true) {
            $access->denied() => $access,
            $user->isKelompok() => Response::deny('Kelompok tidak bisa memverifikasi laporannya sendiri.'),
            $report->status !== ReportStatus::Submitted => Response::deny('Hanya laporan yang sudah diajukan yang bisa ditinjau.'),
            MonthlyReport::separatesDuties() && $report->wasHandledBy($user) => Response::deny(self::HANDLED_MESSAGE),
            default => Response::allow(),
        };
    }

    public function requestRevision(User $user, MonthlyReport $report): Response
    {
        return $this->verify($user, $report);
    }

    /** Khusus Admin: laporan terverifikasi dibuka kembali menjadi "perlu revisi". */
    public function reopen(User $user, MonthlyReport $report): Response
    {
        $access = $this->organizationAccess($user, $report->organization_id);

        return match (true) {
            $access->denied() => $access,
            ! $user->isAdmin() => Response::deny('Hanya Admin distrik yang bisa membuka kembali laporan terverifikasi.'),
            $report->status !== ReportStatus::Verified => Response::deny('Hanya laporan terverifikasi yang bisa dibuka kembali.'),
            default => Response::allow(),
        };
    }

    public function delete(User $user, MonthlyReport $report): bool
    {
        return false;
    }
}
