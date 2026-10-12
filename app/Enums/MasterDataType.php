<?php

namespace App\Enums;

use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Opd;
use App\Models\Village;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Jenis data master yang dikelola Admin dengan pola yang sama. Nilai enum dipakai di URL.
 */
enum MasterDataType: string
{
    case Village = 'kampung';
    case InstitutionType = 'jenis-kelembagaan';
    case BusinessSector = 'bidang-usaha';
    case Opd = 'opd';

    /** Label tab dan judul, mis. "Jenis kelembagaan". */
    public function label(): string
    {
        return match ($this) {
            self::Village => 'Kampung',
            self::InstitutionType => 'Jenis kelembagaan',
            self::BusinessSector => 'Bidang usaha',
            self::Opd => 'OPD',
        };
    }

    /** Dipakai di tengah kalimat, mis. "Tambah jenis kelembagaan". */
    public function noun(): string
    {
        return $this === self::Opd ? 'OPD' : lcfirst($this->label());
    }

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Village => Village::class,
            self::InstitutionType => InstitutionType::class,
            self::BusinessSector => BusinessSector::class,
            self::Opd => Opd::class,
        };
    }

    public function query(): Builder
    {
        return $this->modelClass()::query()->withCount($this->usageRelations());
    }

    public function table(): string
    {
        return (new ($this->modelClass()))->getTable();
    }

    /**
     * Kolom form. 'unique' => nilai tidak boleh kembar; 'column' => tampil sebagai kolom tabel.
     *
     * @return array<string, array{label: string, max?: int, hint?: string, unique?: bool, optional?: bool, textarea?: bool, column?: bool, pattern?: string}>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Village => [
                'name' => ['label' => 'Nama kampung', 'max' => 255, 'unique' => true],
            ],
            self::InstitutionType => [
                'name' => ['label' => 'Nama jenis kelembagaan', 'max' => 255, 'unique' => true, 'hint' => 'Tulis apa adanya, mis. KDMP. Jangan menebak kepanjangan akronim.'],
                'code' => ['label' => 'Kode', 'max' => 30, 'unique' => true, 'column' => true, 'pattern' => '/^[a-z0-9_]+$/', 'hint' => 'Huruf kecil, angka, dan garis bawah, mis. kelompok_tani.'],
                'description' => ['label' => 'Deskripsi singkat', 'max' => 200, 'optional' => true, 'textarea' => true, 'hint' => 'Satu kalimat untuk kartu di beranda. Boleh kosong.'],
            ],
            self::BusinessSector => [
                'name' => ['label' => 'Nama bidang usaha', 'max' => 255, 'unique' => true],
            ],
            self::Opd => [
                'name' => ['label' => 'Nama OPD', 'max' => 255],
                'short_name' => ['label' => 'Nama singkat', 'max' => 50, 'unique' => true, 'column' => true, 'hint' => 'Dipakai di daftar dan nama akun, mis. Dinkopdag.'],
                'description' => ['label' => 'Keterangan', 'max' => 2000, 'optional' => true, 'textarea' => true],
            ],
        };
    }

    /** Kolom yang dicari lewat kotak pencarian. */
    public function searchColumns(): array
    {
        return collect($this->fields())->filter(fn (array $field) => ! ($field['textarea'] ?? false))->keys()->all();
    }

    /** Relasi yang membuat data ini "dipakai" sehingga tidak bisa dihapus. */
    public function usageRelations(): array
    {
        return $this === self::Opd ? ['organizations', 'users', 'issueFollowUps'] : ['organizations'];
    }

    /**
     * Ringkasan pemakaian, mis. "5 kelompok" atau "5 kelompok dan 2 akun"; null bila belum dipakai.
     * Membutuhkan hasil withCount dari query().
     */
    public function usage(Model $record): ?string
    {
        $parts = array_filter([
            $record->organizations_count ? $record->organizations_count.' kelompok' : null,
            ($record->users_count ?? 0) > 0 ? $record->users_count.' akun' : null,
            ($record->issue_follow_ups_count ?? 0) > 0 ? $record->issue_follow_ups_count.' tindak lanjut' : null,
        ]);

        return $parts ? collect($parts)->join(', ', ' dan ') : null;
    }
}
