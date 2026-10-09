<?php

namespace App\Enums;

trait HasOptions
{
    /**
     * @return array<string, string> value => label, untuk komponen x-select
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
