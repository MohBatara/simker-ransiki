<?php

namespace App\Enums;

enum ChangeAction: string
{
    use HasOptions;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Ditambahkan',
            self::Updated => 'Diubah',
            self::Deleted => 'Dihapus',
        };
    }
}
