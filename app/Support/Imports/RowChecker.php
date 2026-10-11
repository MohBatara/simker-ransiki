<?php

namespace App\Support\Imports;

use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\Village;
use App\Support\NumberInput;
use App\Support\SimilarNames;
use Illuminate\Support\Facades\Validator;

/**
 * Pemeriksaan per baris untuk OrganizationImport. Data master dan kelompok yang ada dimuat sekali untuk
 * seluruh file.
 */
class RowChecker
{
    private array $types;

    private array $villages;

    private array $sectors;

    private array $opds;

    /** @var array<int, list<array{id: int, name: string}>> kelompok yang ada, per kampung */
    private array $existing;

    public function __construct()
    {
        $map = fn ($items, string ...$fields) => collect($items)->flatMap(fn ($item) => collect($fields)
            ->filter(fn (string $field) => filled($item->{$field}))
            ->mapWithKeys(fn (string $field) => [OrganizationImport::normalize($item->{$field}) => $item->id]))->all();

        $this->types = $map(InstitutionType::get(['id', 'name', 'code']), 'name', 'code');
        $this->villages = $map(Village::get(['id', 'name']), 'name');
        $this->sectors = $map(BusinessSector::get(['id', 'name']), 'name');
        $this->opds = $map(Opd::get(['id', 'name', 'short_name']), 'name', 'short_name');
        $this->existing = Organization::query()->get(['id', 'name', 'village_id'])->groupBy('village_id')
            ->map(fn ($group) => $group->map->only(['id', 'name'])->values()->all())->all();
    }

    public function check(array $lines): array
    {
        $seen = [];

        return array_map(function (array $line) use (&$seen) {
            [$data, $errors] = $this->parse($line['values']);

            if ($errors !== []) {
                return [...$line, 'data' => null, 'status' => OrganizationImport::FAILED, 'messages' => $errors];
            }

            $warnings = [];
            $key = $data['village_id'].'|'.OrganizationImport::normalize($data['name']);
            if (isset($seen[$key])) {
                $warnings[] = "Nama dan kampung sama dengan baris {$seen[$key]} di file ini.";
            } else {
                $seen[$key] = $line['line'];
            }

            foreach ($this->existing[$data['village_id']] ?? [] as $organization) {
                if (OrganizationImport::normalize($organization['name']) === OrganizationImport::normalize($data['name'])) {
                    $warnings[] = "Sudah ada di database: {$organization['name']} di kampung yang sama.";
                } elseif (SimilarNames::areSimilar($data['name'], $organization['name'])) {
                    $warnings[] = "Mirip dengan {$organization['name']} yang sudah ada di kampung yang sama.";
                }
            }

            return [...$line, 'data' => $data, 'status' => $warnings ? OrganizationImport::WARNING : OrganizationImport::READY, 'messages' => $warnings];
        }, $lines);
    }

    /** @return array{0: array, 1: list<string>} data siap simpan dan daftar kesalahan */
    private function parse(array $values): array
    {
        $errors = [];
        $label = fn (string $key) => OrganizationImport::COLUMNS[$key][0];

        foreach (OrganizationImport::COLUMNS as $key => [$name, $required]) {
            if ($required && ($values[$key] ?? null) === null) {
                $errors[] = "{$name} wajib diisi.";
            }
        }

        $master = function (string $key, array $map) use ($values, $label, &$errors): array {
            $ids = [];
            foreach (array_filter(array_map('trim', explode(';', (string) ($values[$key] ?? '')))) as $item) {
                $id = $map[OrganizationImport::normalize($item)] ?? null;
                $id ? $ids[] = $id : $errors[] = $label($key)." \"{$item}\" tidak ada di data master.";
            }

            return array_values(array_unique($ids));
        };

        $types = $master('type', $this->types);
        $villages = $master('village', $this->villages);
        if (count($types) > 1 || count($villages) > 1) {
            $errors[] = 'Isi satu jenis kelembagaan dan satu kampung saja.';
        }

        $data = [
            'name' => $values['name'] ?? null,
            'institution_type_id' => $types[0] ?? null,
            'village_id' => $villages[0] ?? null,
            'sectors' => $master('sectors', $this->sectors),
            'opds' => $master('opds', $this->opds),
            'reporting_starts_on' => $this->month($values['reporting_starts_on'] ?? null, $errors),
            'leader_name' => $values['leader_name'] ?? null,
            'phone' => $this->phone($values['phone'] ?? null),
            'email' => $values['email'] ?? null,
            'address' => $values['address'] ?? null,
            'founded_year' => $this->integer($values['founded_year'] ?? null),
            'members_count' => $this->integer($values['members_count'] ?? null),
            'workforce_count' => $this->integer($values['workforce_count'] ?? null),
            'description' => $values['description'] ?? null,
        ];

        $validator = Validator::make($data, [
            'name' => ['nullable', 'string', 'max:255'],
            'leader_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'founded_year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'members_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'workforce_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], collect(OrganizationImport::COLUMNS)->map(fn (array $column) => mb_strtolower($column[0]))->all());

        $errors = [...$errors, ...$validator->errors()->all()];

        return [[...$data, 'status' => 'active', 'is_public' => false], $errors];
    }

    /** Angka bulat; teks "1.250" dibaca 1250. Nilai yang bukan angka dikembalikan apa adanya agar gagal validasi. */
    private function integer(?string $value): int|string|null
    {
        if ($value === null) {
            return null;
        }

        $clean = NumberInput::integer($value);

        return is_string($clean) && preg_match('/^-?\d+$/', $clean) ? (int) $clean : $clean;
    }

    /** Nomor yang tersimpan sebagai angka di Excel kehilangan 0 di depan: 81234567 menjadi 081234567. */
    private function phone(?string $value): ?string
    {
        return $value !== null && preg_match('/^8\d{7,12}$/', $value) ? '0'.$value : $value;
    }

    /** "2026-09", "2026-9", "09/2026", atau tanggal dari sel Excel; kosong = bulan ini. */
    private function month(?string $value, array &$errors): ?string
    {
        if ($value === null) {
            return now()->startOfMonth()->toDateString();
        }

        $match = match (true) {
            (bool) preg_match('/^(\d{4})-(\d{1,2})(-\d{1,2})?$/', $value, $parts) => [$parts[1], $parts[2]],
            (bool) preg_match('/^(\d{1,2})[\/-](\d{4})$/', $value, $parts) => [$parts[2], $parts[1]],
            default => null,
        };

        if (! $match || (int) $match[1] < 1 || (int) $match[1] > 12 || (int) $match[0] < 2020 || (int) $match[0] > now()->year + 1) {
            $errors[] = 'Mulai wajib lapor harus berformat TTTT-BB, tahun 2020 sampai '.(now()->year + 1).'.';

            return null;
        }

        return sprintf('%04d-%02d-01', $match[0], $match[1]);
    }
}
