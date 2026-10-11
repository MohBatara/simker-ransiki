<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Organizations\ImportOrganizations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\OrganizationImportRequest;
use App\Support\Exports\Spreadsheet;
use App\Support\Imports\ImportException;
use App\Support\Imports\ImportFile;
use App\Support\Imports\OrganizationImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Impor kelompok dari spreadsheet (khusus Admin): unggah → pratinjau (belum disimpan) → impor dalam satu
 * transaksi → hasil. Setiap langkah membaca ulang file dan memeriksa ulang terhadap database saat itu.
 */
class OrganizationImportController extends Controller
{
    public function create(): View
    {
        return view('admin.organizations.import.create', ['maxRows' => OrganizationImport::MAX_ROWS]);
    }

    /** Template: sheet Data (judul kolom saja) dan sheet Petunjuk (cara isi + nilai valid dari data master). */
    public function template(): BinaryFileResponse
    {
        $guide = [
            ['Cara mengisi'],
            ['1. Isi data di sheet Data mulai baris kedua. Satu baris untuk satu kelompok. Jangan ubah judul kolom.'],
            ['2. Kolom bertanda (wajib) harus diisi. Kolom lain boleh kosong; angka yang kosong tetap kosong, tidak dianggap 0.'],
            ['3. Nama jenis kelembagaan, kampung, bidang usaha, dan OPD harus sama dengan daftar di bawah (huruf besar-kecil tidak berpengaruh).'],
            ['4. Simpan sebagai .xlsx (atau .csv), maksimal 2 MB dan '.OrganizationImport::MAX_ROWS.' baris, lalu unggah di halaman Impor.'],
            ['5. Kelompok hasil impor berstatus Aktif, belum tampil di direktori publik, dan belum punya akun.'],
            [],
            ['Kolom', 'Wajib', 'Keterangan'],
            ...collect(OrganizationImport::COLUMNS)->map(fn (array $column) => [$column[0], $column[1] ? 'Ya' : 'Tidak', $column[2]])->values(),
        ];

        foreach (OrganizationImport::validValues() as $label => $values) {
            $guide[] = [];
            $guide[] = [$label.' yang valid'];
            foreach ($values ?: ['(belum ada di data master)'] as $value) {
                $guide[] = [$value];
            }
        }

        return Spreadsheet::download('template-impor-kelompok.xlsx', [
            ['name' => 'Data', 'header' => OrganizationImport::headers(), 'rows' => []],
            ['name' => 'Petunjuk', 'rows' => $guide],
        ]);
    }

    public function store(OrganizationImportRequest $request): RedirectResponse
    {
        $type = $request->fileType();
        $token = ImportFile::store($request->file('file'), $type, $request->user());
        $meta = ImportFile::meta($token, $request->user());

        // Masalah pada file secara keseluruhan langsung ditampilkan di halaman unggah.
        try {
            OrganizationImport::read(ImportFile::sourcePath($token, $meta), $type);
        } catch (ImportException $exception) {
            ImportFile::discard($token, $meta);

            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return redirect()->route('admin.organizations.import.preview', $token);
    }

    public function preview(Request $request, string $token): View|RedirectResponse
    {
        [$meta, $import] = $this->load($request, $token);

        if ($meta['result'] !== null) {
            return redirect()->route('admin.organizations.import.result', $token);
        }
        if (! $import) {
            return redirect()->route('admin.organizations.import.create')->withErrors(['file' => 'File tidak bisa dibaca lagi. Unggah ulang.']);
        }

        return view('admin.organizations.import.preview', [
            'token' => $token,
            'fileName' => $meta['name'],
            'rows' => $import->rows,
            'counts' => $import->counts(),
        ]);
    }

    public function import(Request $request, string $token, ImportOrganizations $importer): RedirectResponse
    {
        [$meta, $import] = $this->load($request, $token);

        if ($meta['result'] !== null) {
            return redirect()->route('admin.organizations.import.result', $token);
        }
        if (! $import) {
            return redirect()->route('admin.organizations.import.create')->withErrors(['file' => 'File tidak bisa dibaca lagi. Unggah ulang.']);
        }

        $accepted = collect((array) $request->input('include', []))->map(fn ($line) => (int) $line)->all();
        $counts = $import->counts();
        $selected = collect($import->rows)->where('status', OrganizationImport::WARNING)->whereIn('line', $accepted)->count();
        if ($counts[OrganizationImport::READY] + $selected === 0) {
            return back()->with('error', 'Tidak ada baris yang bisa diimpor. Perbaiki file lalu unggah ulang.');
        }

        try {
            $result = $importer->handle($request->user(), $import, $accepted);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Impor dibatalkan karena terjadi kesalahan saat menyimpan. Tidak ada data yang disimpan. Coba lagi.');
        }

        ImportFile::finish($token, $meta, $result);

        return redirect()->route('admin.organizations.import.result', $token)->with('status', $result['imported'].' kelompok diimpor.');
    }

    public function result(Request $request, string $token): View|RedirectResponse
    {
        $meta = ImportFile::meta($token, $request->user()) ?? abort(404);

        if ($meta['result'] === null) {
            return redirect()->route('admin.organizations.import.preview', $token);
        }

        return view('admin.organizations.import.result', [
            'token' => $token,
            'fileName' => $meta['name'],
            'result' => $meta['result'],
        ]);
    }

    /** Daftar baris yang gagal beserta alasannya, dalam format yang sama dengan template agar mudah diperbaiki. */
    public function failed(Request $request, string $token): BinaryFileResponse
    {
        $meta = ImportFile::meta($token, $request->user()) ?? abort(404);
        $failed = $meta['result']['failed'] ?? abort(404);
        $keys = array_keys(OrganizationImport::COLUMNS);

        return Spreadsheet::download('impor-kelompok-gagal-'.now()->format('Y-m-d').'.xlsx', [[
            'name' => 'Data',
            'header' => ['Baris', 'Alasan', ...OrganizationImport::headers()],
            'rows' => collect($failed)->map(fn (array $row) => [
                $row['line'],
                implode(' ', $row['messages']),
                ...array_map(fn (string $key) => $row['values'][$key] ?? null, $keys),
            ]),
        ]]);
    }

    /** @return array{0: array, 1: ?OrganizationImport} */
    private function load(Request $request, string $token): array
    {
        $meta = ImportFile::meta($token, $request->user()) ?? abort(404);

        if ($meta['result'] !== null) {
            return [$meta, null];
        }

        $path = ImportFile::sourcePath($token, $meta);

        try {
            return [$meta, $path ? OrganizationImport::read($path, $meta['type']) : null];
        } catch (ImportException) {
            return [$meta, null];
        }
    }
}
