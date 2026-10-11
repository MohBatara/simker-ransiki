<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menu sidebar dan navigasi bawah per peran (CLAUDE.md Bagian 6).
 * Item yang route-nya belum dibuat disembunyikan, sehingga menu bertambah seiring fitur selesai.
 */
class Navigation
{
    /**
     * @return list<array{label: string, short: ?string, route: string, icon: string, primary: bool, active: string}>
     */
    public static function definitions(Role $role): array
    {
        $notifications = self::item('Notifikasi', 'notifications.index', 'bell', active: 'notifications.*');
        $account = self::item('Akun Saya', 'account.password.edit', 'user-circle', 'Akun', active: 'account.*');

        return match ($role) {
            Role::Kelompok => [
                self::item('Dasbor', 'kelompok.dashboard', 'home', primary: true),
                self::item('Profil Kelompok', 'kelompok.profile.show', 'building-office', 'Profil', true, 'kelompok.profile.*'),
                self::item('Produk', 'kelompok.products.index', 'cube', primary: true, active: 'kelompok.products.*'),
                self::item('Legalitas & Izin', 'kelompok.licenses.index', 'document-check', 'Izin', active: 'kelompok.licenses.*'),
                self::item('Laporan Bulanan', 'kelompok.reports.index', 'document-text', 'Laporan', true, 'kelompok.reports.*'),
                $notifications,
                $account,
            ],
            Role::Opd => [
                self::item('Dasbor', 'opd.dashboard', 'home', primary: true),
                self::item('Kelompok Binaan', 'opd.organizations.index', 'user-group', 'Kelompok', true, 'opd.organizations.*'),
                self::item('Verifikasi Laporan', 'opd.verifications.index', 'clipboard-document-check', 'Verifikasi', true, 'opd.verifications.*'),
                self::item('Semua Laporan', 'opd.reports.index', 'document-text', 'Laporan', active: 'opd.reports.*'),
                self::item('Masalah & Kebutuhan', 'opd.issues.index', 'exclamation-circle', 'Masalah', true, 'opd.issues.*'),
                self::item('Statistik', 'opd.statistics.economy', 'chart-bar', active: 'opd.statistics.*'),
                $notifications,
                $account,
            ],
            Role::Admin => [
                self::item('Dasbor Distrik', 'admin.dashboard', 'home', 'Dasbor', true),
                self::item('Semua Kelompok', 'admin.organizations.index', 'user-group', 'Kelompok', true, 'admin.organizations.*'),
                self::item('Semua Laporan', 'admin.reports.index', 'document-text', 'Laporan', true, 'admin.reports.*'),
                self::item('Masalah & Kebutuhan', 'admin.issues.index', 'exclamation-circle', 'Masalah', active: 'admin.issues.*'),
                self::item('Statistik', 'admin.statistics.economy', 'chart-bar', active: 'admin.statistics.*'),
                self::item('Pengguna', 'admin.users.index', 'users', primary: true, active: 'admin.users.*'),
                self::item('Data Master', 'admin.master.index', 'circle-stack', 'Master', active: 'admin.master.*'),
                self::item('Konten Website', 'admin.content.edit', 'globe-alt', 'Konten', active: 'admin.content.*'),
                $notifications,
            ],
        };
    }

    /**
     * Item siap pakai untuk layout app: hanya route yang ada, lengkap dengan href dan status aktif.
     */
    public static function for(User $user): array
    {
        return collect(self::definitions($user->role))
            ->filter(fn (array $item) => Route::has($item['route']))
            ->map(fn (array $item) => array_merge($item, [
                'href' => route($item['route']),
                'active' => request()->routeIs($item['active']),
            ]))
            ->values()
            ->all();
    }

    private static function item(string $label, string $route, string $icon, ?string $short = null, bool $primary = false, ?string $active = null): array
    {
        return [
            'label' => $label,
            'short' => $short,
            'route' => $route,
            'icon' => $icon,
            'primary' => $primary,
            'active' => $active ?? $route,
        ];
    }
}
