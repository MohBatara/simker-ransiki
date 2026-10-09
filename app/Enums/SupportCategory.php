<?php

namespace App\Enums;

enum SupportCategory: string
{
    use HasOptions;

    case BantuanAlat = 'bantuan_alat';
    case Pelatihan = 'pelatihan';
    case Modal = 'modal';
    case Pendampingan = 'pendampingan';
    case Pemasaran = 'pemasaran';
    case Legalitas = 'legalitas';
    case AksesPasar = 'akses_pasar';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::BantuanAlat => 'Bantuan alat',
            self::Pelatihan => 'Pelatihan',
            self::Modal => 'Modal',
            self::Pendampingan => 'Pendampingan',
            self::Pemasaran => 'Pemasaran',
            self::Legalitas => 'Legalitas',
            self::AksesPasar => 'Akses pasar',
            self::Lainnya => 'Lainnya',
        };
    }
}
