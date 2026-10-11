<?php

namespace App\Actions\Organizations;

use App\Models\User;
use App\Support\DuplicateAlert;
use App\Support\Imports\OrganizationImport;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan baris impor kelompok dalam SATU transaksi: bila satu baris gagal disimpan, tidak ada yang
 * tersimpan. Baris "Siap" selalu diimpor; baris "Peringatan" hanya bila dipilih Admin; baris "Gagal" tidak
 * pernah. Kelompok baru berstatus aktif, tidak tampil publik, dan tanpa akun pengguna.
 */
class ImportOrganizations
{
    public function __construct(private SaveOrganization $save) {}

    /**
     * @param  list<int>  $acceptedWarnings  nomor baris "Peringatan" yang tetap diimpor
     * @return array{imported: int, skipped: int, failed: list<array{line: int, values: array, messages: list<string>}>}
     */
    public function handle(User $admin, OrganizationImport $import, array $acceptedWarnings): array
    {
        $rows = collect($import->rows);
        $chosen = $rows->filter(fn (array $row) => $row['status'] === OrganizationImport::READY
            || ($row['status'] === OrganizationImport::WARNING && in_array($row['line'], $acceptedWarnings, true)));

        $saved = DB::transaction(fn () => $chosen->map(fn (array $row) => $this->save->handle($admin, $row['data']))->values());

        DuplicateAlert::imported($admin, $saved);

        return [
            'imported' => $chosen->count(),
            'skipped' => $rows->where('status', OrganizationImport::WARNING)->count() - $chosen->where('status', OrganizationImport::WARNING)->count(),
            'failed' => $rows->where('status', OrganizationImport::FAILED)
                ->map(fn (array $row) => ['line' => $row['line'], 'values' => $row['values'], 'messages' => $row['messages']])
                ->values()->all(),
        ];
    }
}
