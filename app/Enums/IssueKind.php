<?php

namespace App\Enums;

enum IssueKind: string
{
    use HasOptions;

    case Problem = 'problem';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Problem => 'Masalah',
            self::Support => 'Kebutuhan',
        };
    }
}
