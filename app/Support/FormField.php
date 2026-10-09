<?php

namespace App\Support;

class FormField
{
    /**
     * Convert an input name to its validation key: "items[0][qty]" becomes "items.0.qty",
     * "sectors[]" becomes "sectors".
     */
    public static function key(string $name): string
    {
        return trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    }

    public static function id(string $name): string
    {
        return str_replace('.', '-', self::key($name));
    }
}
