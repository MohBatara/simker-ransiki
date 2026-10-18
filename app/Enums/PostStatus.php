<?php

namespace App\Enums;

enum PostStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Terbit',
        };
    }
}
