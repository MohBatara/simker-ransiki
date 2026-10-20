<?php

use Symfony\Component\Finder\Finder;

/*
 * Blade tidak mengompilasi direktif (@js, @json, @lang) di dalam atribut tag komponen <x-...>: teksnya terkirim mentah,
 * Alpine gagal membaca ekspresinya, dan tombolnya mati (mis. Batal di popup konfirmasi). Pakai {{ Js::from(...) }}.
 */
test('direktif Blade tidak dipakai di atribut tag komponen', function () {
    $found = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
        $source = $file->getContents();
        preg_match_all('/<x-[\w.:-]+/', $source, $tags, PREG_OFFSET_CAPTURE);

        foreach ($tags[0] as [$name, $offset]) {
            // Ujung tag: ">" pertama di luar tanda kutip.
            $quote = null;
            for ($end = $offset; $end < strlen($source); $end++) {
                $char = $source[$end];
                if ($quote) {
                    $quote = $char === $quote ? null : $quote;
                } elseif ($char === '"' || $char === "'") {
                    $quote = $char;
                } elseif ($char === '>') {
                    break;
                }
            }

            if (preg_match('/@(js|json|lang|choice)\(/', substr($source, $offset, $end - $offset))) {
                $found[] = $file->getRelativePathname().':'.(substr_count(substr($source, 0, $offset), "\n") + 1).' '.$name;
            }
        }
    }

    expect($found)->toBe([]);
});
