<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

class ComponentGalleryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            function (Request $request, Closure $next) {
                abort_unless(app()->isLocal(), 404);

                return $next($request);
            },
        ];
    }

    public function app(): View
    {
        // Contoh pesan validasi agar tampilan error pada form dapat ditinjau.
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'contoh_error' => 'Nama kelompok wajib diisi.',
        ])));

        return view('dev.komponen', [
            'navigation' => $this->sampleNavigation(),
            'reports' => [
                ['period' => '2026-09-01', 'organization' => 'Kelompok Contoh A', 'status' => ['Menunggu verifikasi', 'warning'], 'revenue' => 1250000, 'submitted_at' => '2026-10-05'],
                ['period' => '2026-09-01', 'organization' => 'Kelompok Contoh B', 'status' => ['Perlu revisi', 'orange'], 'revenue' => null, 'submitted_at' => '2026-10-03'],
                ['period' => '2026-08-01', 'organization' => 'Kelompok Contoh A', 'status' => ['Terverifikasi', 'success'], 'revenue' => 980000, 'submitted_at' => '2026-09-08'],
                ['period' => '2026-08-01', 'organization' => 'Kelompok Contoh C', 'status' => ['Draf', 'neutral'], 'revenue' => 0, 'submitted_at' => null],
            ],
        ]);
    }

    public function form(): View
    {
        return view('dev.form', ['navigation' => $this->sampleNavigation()]);
    }

    public function public(): View
    {
        return view('dev.publik');
    }

    public function guest(): View
    {
        return view('dev.masuk');
    }

    /**
     * Menu Kelompok (CLAUDE.md Bagian 6) sebagai contoh; menu sungguhan dibuat bersama fitur login.
     */
    private function sampleNavigation(): array
    {
        return [
            ['label' => 'Dasbor', 'icon' => 'home', 'href' => '#', 'primary' => true],
            ['label' => 'Profil Kelompok', 'short' => 'Profil', 'icon' => 'building-office', 'href' => '#', 'primary' => true],
            ['label' => 'Produk', 'icon' => 'cube', 'href' => '#', 'primary' => true],
            ['label' => 'Legalitas & Izin', 'short' => 'Izin', 'icon' => 'document-check', 'href' => '#'],
            ['label' => 'Laporan Bulanan', 'short' => 'Laporan', 'icon' => 'document-text', 'href' => '#', 'primary' => true],
            ['label' => 'Notifikasi', 'icon' => 'bell', 'href' => '#'],
            ['label' => 'Akun Saya', 'short' => 'Akun', 'icon' => 'user-circle', 'href' => '#'],
            ['label' => 'Komponen', 'icon' => 'squares-2x2', 'href' => route('dev.components.app'), 'active' => true],
        ];
    }
}
