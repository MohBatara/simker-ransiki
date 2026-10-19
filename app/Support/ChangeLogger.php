<?php

namespace App\Support;

use App\Enums\ChangeAction;
use App\Models\ChangeLog;
use App\Models\InstitutionType;
use App\Models\License;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Village;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat perubahan profil kelompok, produk, dan izin (tab "Riwayat" di detail kelompok). Hanya kolom
 * yang terdaftar di FIELDS yang dicatat, sehingga kolom teknis (updated_at, updated_by, path foto, dsb.)
 * dan isi file tidak pernah masuk. Nilai disimpan sebagai teks siap tampil (nama kampung, label status).
 */
class ChangeLogger
{
    /** @var array<class-string<Model>, array<string, string>> kolom => label */
    /** Label riwayat untuk catatan lewat note() (tidak dicatat otomatis oleh observer). */
    public const NOTE_LABELS = ['profile_status' => 'Verifikasi profil'];

    public const FIELDS = [
        Organization::class => [
            'name' => 'Nama kelompok',
            'institution_type_id' => 'Jenis kelembagaan',
            'village_id' => 'Kampung',
            'address' => 'Alamat',
            'founded_year' => 'Tahun berdiri',
            'leader_name' => 'Ketua',
            'phone' => 'Telepon',
            'email' => 'Email',
            'members_count' => 'Jumlah anggota',
            'description' => 'Deskripsi',
            'production_capacity' => 'Kapasitas produksi',
            'markets' => 'Pasar',
            'workforce_count' => 'Tenaga kerja',
            'assets' => 'Aset',
            'facilities' => 'Fasilitas',
            'equipment' => 'Peralatan',
            'legal_status' => 'Status hukum',
            'legal_number' => 'Nomor legalitas',
            'legal_notes' => 'Catatan legalitas',
            'status' => 'Status kelompok',
            'reporting_starts_on' => 'Mulai wajib lapor',
            'is_public' => 'Tampil di direktori publik',
            'show_contact_publicly' => 'Kontak tampil di publik',
            'profile_status' => 'Verifikasi profil',
            // Relasi, dicatat lewat relation() dari App\Actions\Organizations\SaveOrganization.
            'sectors' => 'Bidang usaha',
            'opds' => 'OPD pembina',
        ],
        Product::class => [
            'name' => 'Nama produk',
            'description' => 'Keterangan',
            'unit' => 'Satuan',
            'capacity' => 'Kapasitas',
            'is_featured' => 'Produk unggulan',
            'is_public' => 'Tampil di publik',
        ],
        License::class => [
            'type' => 'Jenis izin',
            'number' => 'Nomor izin',
            'status' => 'Status izin',
            'submitted_on' => 'Tanggal diajukan',
            'issued_on' => 'Tanggal terbit',
            'expires_on' => 'Berlaku sampai',
            'notes' => 'Catatan',
        ],
    ];

    public static function created(Model $model): void
    {
        self::write($model, ChangeAction::Created);
    }

    public static function deleted(Model $model): void
    {
        self::write($model, ChangeAction::Deleted);
    }

    /**
     * Dipanggil dari event "updated": getChanges() berisi nilai baru, getOriginal() masih nilai lama.
     *
     * @return list<string> kolom yang dicatat
     */
    public static function updated(Model $model): array
    {
        $fields = array_intersect_key($model->getChanges(), self::FIELDS[$model::class]);

        foreach (array_keys($fields) as $field) {
            $old = self::display($field, $model->getOriginal($field));
            $new = self::display($field, $model->getAttribute($field));

            if ($old !== $new) {
                self::write($model, ChangeAction::Updated, $field, $old, $new);
            }
        }

        return array_keys($fields);
    }

    /**
     * Perubahan relasi banyak-ke-banyak (bidang usaha, OPD pembina) dalam bentuk daftar nama.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    public static function relation(Organization $organization, string $field, array $before, array $after): bool
    {
        sort($before);
        sort($after);

        if ($before === $after) {
            return false;
        }

        self::write($organization, ChangeAction::Updated, $field, implode(', ', $before) ?: null, implode(', ', $after) ?: null);

        return true;
    }

    /** Catatan riwayat yang bukan perubahan kolom biasa (mis. verifikasi profil otomatis saat dibuat petugas). */
    public static function note(Organization $organization, string $field, string $text): void
    {
        self::write($organization, ChangeAction::Updated, $field, '', $text);
    }

    public static function label(string $type, ?string $field): ?string
    {
        $class = Organization::getActualClassNameForMorph($type);

        return $field ? (self::FIELDS[$class][$field] ?? self::NOTE_LABELS[$field] ?? $field) : null;
    }

    private static function write(Model $model, ChangeAction $action, ?string $field = null, ?string $old = null, ?string $new = null): void
    {
        ChangeLog::create([
            'organization_id' => $model instanceof Organization ? $model->id : $model->organization_id,
            'loggable_type' => $model->getMorphClass(),
            'loggable_id' => $model->getKey(),
            'subject' => match (true) {
                $model instanceof Product => 'Produk: '.$model->name,
                $model instanceof License => 'Izin: '.$model->type,
                default => 'Profil',
            },
            'action' => $action,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'user_id' => auth()->id(),
        ]);
    }

    private static function display(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (true) {
            $field === 'institution_type_id' => InstitutionType::whereKey($value)->value('name') ?? (string) $value,
            $field === 'village_id' => Village::whereKey($value)->value('name') ?? (string) $value,
            $field === 'reporting_starts_on' => Format::month($value),
            $value instanceof BackedEnum => method_exists($value, 'label') ? $value->label() : $value->value,
            $value instanceof CarbonInterface => Format::date($value),
            is_bool($value) => $value ? 'Ya' : 'Tidak',
            default => (string) $value,
        };
    }
}
