<?php

namespace App\Support\Exports;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\Response;

/**
 * Dokumen PDF siap cetak (hitam-putih, A4). Kepala berisi judul, periode, dan "Dicetak pada ... WIT oleh
 * ..."; kaki berisi nomor halaman dan pengingat bahwa angka resmi hanya dari laporan terverifikasi.
 */
class Pdf
{
    public static function download(string $view, array $data, string $filename, User $printedBy): Response
    {
        return response(self::render($view, $data, $printedBy), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function render(string $view, array $data, User $printedBy): string
    {
        $pdf = DomPdf::loadView($view, ['printedLine' => self::printedLine($printedBy), ...$data])->setPaper('a4');
        $pdf->render();

        // Nomor halaman lewat kanvas dompdf: CSS counter(pages) tidak didukung dompdf.
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $text = 'Halaman {PAGE_NUM} dari {PAGE_COUNT}';
        $width = $dompdf->getFontMetrics()->getTextWidth('Halaman 99 dari 99', $font, 8);
        $canvas->page_text($canvas->get_width() - 45 - $width, $canvas->get_height() - 44, $text, $font, 8, [0.27, 0.27, 0.27]);

        return $pdf->output();
    }

    /** "Dicetak pada 11 Oktober 2026 14.30 WIT oleh Nama". Zona waktu laporan selalu WIT. */
    public static function printedLine(User $user): string
    {
        $now = now()->setTimezone(config('simker.reports.timezone'));

        return 'Dicetak pada '.$now->translatedFormat('j F Y H.i').' WIT oleh '.$user->name;
    }
}
