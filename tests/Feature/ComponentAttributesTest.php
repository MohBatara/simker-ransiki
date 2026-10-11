<?php

use Symfony\Component\Finder\Finder;

test('atribut href/title/description komponen tidak memakai {{ }} (nilai ter-escape ganda)', function () {
    // Blade sudah meng-escape nilai {{ }} di atribut komponen; prop yang dicetak lagi dengan {{ }} menjadi "&amp;amp;".
    $offenders = collect(Finder::create()->files()->in(resource_path('views'))->name('*.blade.php'))
        ->flatMap(function ($file) {
            preg_match_all('/<x-[\w.:-]+(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/s', $file->getContents(), $tags);

            return collect($tags[0])
                ->filter(fn ($tag) => preg_match('/(?<![:\w])(href|title|description)="[^"]*\{\{/', $tag))
                ->map(fn () => $file->getRelativePathname());
        })
        ->unique()
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
