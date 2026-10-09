<?php

namespace App\Enums;

enum AttachmentVisibility: string
{
    use HasOptions;

    case Private = 'private';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Privat',
            self::Public => 'Publik',
        };
    }
}
