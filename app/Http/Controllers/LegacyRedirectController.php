<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * URL lama area Admin/OPD sebelum menu digabung menjadi tab: 301 ke URL baru, sisa path dan query string ikut
 * (mis. /opd/masalah/12?dari=x → /opd/laporan/masalah/12?dari=x). Hak akses tetap diperiksa di URL tujuan.
 */
class LegacyRedirectController
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Parameter dibaca dari route menurut nama: nilai defaults (to, index) ikut terkirim sebagai argumen posisi.
        $route = $request->route();
        $area = $route->parameter('area');
        $rest = $route->parameter('rest');
        $target = $rest !== null && $rest !== ''
            ? $route->defaults['to'].'/'.$rest
            : ($route->defaults['index'] ?? $route->defaults['to']);
        $query = $request->getQueryString();

        return redirect()->to('/'.$area.$target.($query ? '?'.$query : ''), 301);
    }
}
