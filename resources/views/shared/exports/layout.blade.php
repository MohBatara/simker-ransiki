{{--
    Kerangka PDF (dompdf): hitam-putih, A4, huruf DejaVu Sans (bawaan dompdf, mendukung semua huruf Indonesia).
    Variabel: $documentTitle, $subtitle (kelompok/cakupan), $periodLabel, $printedLine.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }}</title>
    <style>
        @page { margin: 22mm 16mm 20mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #000; line-height: 1.4; }
        header.doc { border-bottom: 1px solid #000; padding-bottom: 6pt; margin-bottom: 12pt; }
        header.doc h1 { font-size: 15pt; margin: 0 0 2pt; font-weight: bold; }
        header.doc p { margin: 0; }
        .muted { color: #444; }
        footer.doc { position: fixed; bottom: -12mm; left: 0; right: 0; height: 8mm; font-size: 8pt; color: #444; border-top: 0.5pt solid #888; padding-top: 3pt; }
        h2 { font-size: 11pt; margin: 14pt 0 5pt; font-weight: bold; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
        th, td { border: 0.5pt solid #777; padding: 3pt 4pt; text-align: left; vertical-align: top; }
        th { background: #e8e8e8; font-weight: bold; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr { page-break-inside: avoid; }
        dl { margin: 0; }
        .pairs td:first-child { width: 38%; color: #333; }
        .note { border: 0.5pt solid #777; padding: 4pt 6pt; margin: 4pt 0; white-space: pre-line; }
        .text { white-space: pre-line; }
        .empty { color: #444; font-style: italic; }
    </style>
</head>
<body>
    <footer class="doc">
        {{-- Nomor halaman ditulis App\Support\Exports\Pdf::render di sisi kanan kaki. --}}
        Angka resmi hanya dari laporan terverifikasi.
    </footer>

    <header class="doc">
        <h1>{{ $documentTitle }}</h1>
        @isset($subtitle)
            <p>{{ $subtitle }}</p>
        @endisset
        <p>Periode: {{ $periodLabel }}</p>
        <p class="muted">{{ $printedLine }}</p>
    </header>

    @yield('content')
</body>
</html>
