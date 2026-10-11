<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk semua respons, dan di produksi: alihkan HTTP ke HTTPS + HSTS.
 * Header yang sudah diisi controller (mis. CSP sandbox pada lampiran PDF) tidak ditimpa.
 * Di belakang proxy/CDN, isi TRUSTED_PROXIES agar permintaan HTTPS dikenali (config/trustedproxy.php).
 */
class SecureResponse
{
    private const HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $forceHttps = app()->isProduction() && config('simker.security.force_https');

        if ($forceHttps && ! $request->secure()) {
            // 308 untuk POST dsb. agar metode dan isi kiriman tidak berubah.
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), $request->isMethodSafe() ? 301 : 308);
        }

        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($forceHttps) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
