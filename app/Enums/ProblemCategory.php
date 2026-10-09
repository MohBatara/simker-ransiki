<?php

namespace App\Enums;

enum ProblemCategory: string
{
    use HasOptions;

    case Modal = 'modal';
    case BahanBaku = 'bahan_baku';
    case Peralatan = 'peralatan';
    case Sdm = 'sdm';
    case Pemasaran = 'pemasaran';
    case Transportasi = 'transportasi';
    case Legalitas = 'legalitas';
    case Produksi = 'produksi';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Modal => 'Modal',
            self::BahanBaku => 'Bahan baku',
            self::Peralatan => 'Peralatan',
            self::Sdm => 'SDM',
            self::Pemasaran => 'Pemasaran',
            self::Transportasi => 'Transportasi',
            self::Legalitas => 'Legalitas',
            self::Produksi => 'Produksi',
            self::Lainnya => 'Lainnya',
        };
    }
}
