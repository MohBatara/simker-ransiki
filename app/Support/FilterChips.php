<?php

namespace App\Support;

/**
 * Chip filter aktif untuk x-filter-summary. Setiap chip menaut ke URL yang sama tanpa filter itu.
 */
class FilterChips
{
    /**
     * @param  array<string, array{0: ?string, 1: array|string|null, 2?: list<string>}>  $definitions
     *                                                                                                 kunci => [nama, pilihan nilai=>label (null = tampilkan nilainya, string = label tetap), kunci lain yang ikut dihapus]
     * @return list<array{label: string, href: string}>
     */
    public static function from(array $filters, array $definitions): array
    {
        $chips = [];

        foreach ($definitions as $key => $definition) {
            [$name, $options] = $definition;
            $value = $filters[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $label = match (true) {
                is_array($options) => $options[$value] ?? null,
                is_string($options) => $options,
                default => '“'.trim($value).'”',
            };

            if ($label === null) {
                continue;
            }

            $chips[] = [
                'label' => $name ? "{$name}: {$label}" : $label,
                'href' => request()->fullUrlWithoutQuery([$key, ...($definition[2] ?? []), 'page']),
            ];
        }

        return $chips;
    }
}
