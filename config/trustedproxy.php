<?php

return [

    /*
    | Proxy/CDN di depan aplikasi (dibaca middleware TrustProxies bawaan Laravel). Kosong = tidak ada.
    | Isi "*" bila situs memakai CDN Hostinger atau Cloudflare, agar HTTPS dan IP pengunjung terbaca benar
    | (dipakai paksa HTTPS dan batas percobaan masuk).
    */
    'proxies' => env('TRUSTED_PROXIES'),

];
