<?php

/*
 * Bantuan tes browser: status popup (<dialog data-modal="...">) dan kunci gulir halaman dibaca langsung dari DOM.
 */


function assertPopupOpen(mixed $page, string $name): mixed
{
    return $page->assertScript("!! document.querySelector('dialog[data-modal=\"{$name}\"]')?.open", true);
}

function assertPopupClosed(mixed $page, string $name): mixed
{
    return $page->assertScript("!! document.querySelector('dialog[data-modal=\"{$name}\"]')?.open", false)
        // Setelah popup tertutup, halaman bisa digulir lagi.
        ->assertScript("document.documentElement.classList.contains('modal-open')", false);
}

/** Klik berurutan (selector CSS atau teks) untuk membuka popup. */
function openPopup(mixed $page, array $steps, string $name): mixed
{
    foreach ($steps as $step) {
        $page->click($step);
    }

    return assertPopupOpen($page->wait(0.3), $name);
}

/** Teks ada di dalam popup. Pesan wajib sengaja tampil dua kali (ringkasan + di bawah isian), jadi tidak memakai assertSeeIn. */
function assertPopupHasText(mixed $page, string $name, string $text): mixed
{
    return $page->assertScript("document.querySelector('dialog[data-modal=\"{$name}\"]').textContent.includes(".json_encode($text).')', true);
}
