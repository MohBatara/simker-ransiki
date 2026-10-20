<?php

namespace App\Support;

use App\Enums\ProfileStatus;
use App\Enums\Role;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menu sidebar, navigasi bawah, dan tab per menu (CLAUDE.md Bagian 6).
 * Admin dan OPD: menu berisi beberapa tab (setiap tab punya URL sendiri), badge jumlah yang menunggu di menu Kelompok
 * dan Laporan. Item yang route-nya belum dibuat disembunyikan.
 */
class Navigation
{
    /**
     * Menu bertab Admin/OPD. Badge: 'reports' (laporan menunggu verifikasi) atau 'profiles' (profil menunggu verifikasi).
     *
     * @return array<string, array{label: string, short: ?string, icon: string, primary: bool, badge: ?string, active: list<string>, tabs: array<string, array{label: string, route: string, badge?: string}>}>
     */
    public static function sections(Role $role): array
    {
        if (! in_array($role, [Role::Admin, Role::Opd], true)) {
            return [];
        }

        $area = $role->area();
        $isAdmin = $role === Role::Admin;

        $sections = [
            'dasbor' => self::section('Dasbor', 'home', [
                'ringkasan' => ['label' => 'Ringkasan', 'route' => "{$area}.dashboard"],
                'ekonomi' => ['label' => 'Ekonomi', 'route' => "{$area}.statistics.economy"],
                'legalitas' => ['label' => 'Legalitas', 'route' => "{$area}.statistics.legal"],
            ], ["{$area}.dashboard", "{$area}.statistics.*"], group: 1),
            'kelompok' => self::section($isAdmin ? 'Kelompok' : 'Kelompok binaan', 'user-group', array_filter([
                ($isAdmin ? 'semua' : 'daftar') => ['label' => $isAdmin ? 'Semua kelompok' : 'Daftar', 'route' => "{$area}.organizations.index"],
                // Angka tab = total antrean (perubahan + belum pernah diverifikasi); badge menu = hanya perubahan menunggu.
                'profil' => ['label' => 'Verifikasi profil', 'route' => "{$area}.profile-verifications.index", 'badge' => 'profile_queue'],
                'ganda' => $isAdmin ? ['label' => 'Kemungkinan data ganda', 'route' => 'admin.organizations.duplicates.index'] : null,
            ]), ["{$area}.organizations.*", "{$area}.profile-verifications.*"], short: 'Kelompok', badge: 'profiles', group: 2),
            'laporan' => self::section('Laporan', 'document-text', [
                'semua' => ['label' => 'Semua laporan', 'route' => "{$area}.reports.index"],
                'verifikasi' => ['label' => 'Perlu diverifikasi', 'route' => "{$area}.verifications.index", 'badge' => 'reports'],
                'masalah' => ['label' => 'Masalah & kebutuhan', 'route' => "{$area}.issues.index"],
            ], ["{$area}.reports.*", "{$area}.verifications.*", "{$area}.issues.*"], badge: 'reports', group: 2),
        ];

        if ($isAdmin) {
            $sections['website'] = self::section('Website', 'globe-alt', [
                'beranda' => ['label' => 'Konten beranda', 'route' => 'admin.content.edit'],
                'profil' => ['label' => 'Profil Ransiki', 'route' => 'admin.content.profile'],
                'halaman' => ['label' => 'Halaman & kontak', 'route' => 'admin.content.pages'],
                'berita' => ['label' => 'Berita & kegiatan', 'route' => 'admin.posts.index'],
            ], ['admin.content.*', 'admin.posts.*'], group: 3);
            $sections['pengaturan'] = self::section('Pengaturan', 'cog-6-tooth', [
                'pengguna' => ['label' => 'Pengguna', 'route' => 'admin.users.index'],
                'master' => ['label' => 'Data master', 'route' => 'admin.master.index'],
            ], ['admin.users.*', 'admin.master.*'], primary: false, group: 3);
        }

        return $sections;
    }

    /**
     * @return list<array{label: string, short: ?string, route: string, icon: string, primary: bool, active: list<string>, badge: ?string, sidebar: bool}>
     */
    public static function definitions(Role $role): array
    {
        $notifications = self::item('Notifikasi', 'notifications.index', 'bell', active: 'notifications.*');
        $account = self::item('Akun saya', 'account.password.edit', 'user-circle', 'Akun', active: 'account.*');

        if ($role === Role::Kelompok) {
            return [
                self::item('Dasbor', 'kelompok.dashboard', 'home', primary: true),
                self::item('Profil kelompok', 'kelompok.profile.show', 'building-office', 'Profil', true, 'kelompok.profile.*'),
                self::item('Produk', 'kelompok.products.index', 'cube', primary: true, active: 'kelompok.products.*'),
                self::item('Legalitas dan izin', 'kelompok.licenses.index', 'document-check', 'Izin', active: 'kelompok.licenses.*'),
                self::item('Laporan bulanan', 'kelompok.reports.index', 'document-text', 'Laporan', true, 'kelompok.reports.*'),
                $notifications,
                $account,
            ];
        }

        $items = collect(self::sections($role))->map(fn (array $section) => [
            'label' => $section['label'],
            'short' => $section['short'],
            'route' => reset($section['tabs'])['route'],
            'icon' => $section['icon'],
            'primary' => $section['primary'],
            'active' => $section['active'],
            'badge' => $section['badge'],
            'sidebar' => true,
            'group' => $section['group'],
        ])->values()->all();

        // Akun saya tidak di sidebar (ada di menu profil bilah atas), hanya di navigasi bawah HP.
        return [...$items, $notifications, array_merge($account, ['sidebar' => false])];
    }

    /**
     * Item siap pakai untuk layout app: hanya route yang ada, lengkap dengan href, status aktif, dan angka badge.
     */
    public static function for(User $user): array
    {
        $counts = self::counts($user);

        return collect(self::definitions($user->role))
            ->filter(fn (array $item) => Route::has($item['route']))
            ->map(fn (array $item) => array_merge($item, [
                'href' => route($item['route']),
                'active' => request()->routeIs(...$item['active']),
                'count' => $item['badge'] ? ($counts[$item['badge']] ?? 0) : 0,
            ]))
            ->values()
            ->all();
    }

    /**
     * Tab satu menu untuk layout app.
     *
     * @return array{label: string, href: string, tabs: array<string, array{label: string, href: string, count: ?int}>}|null
     */
    public static function tabs(User $user, string $section): ?array
    {
        $definition = self::sections($user->role)[$section] ?? null;

        if (! $definition) {
            return null;
        }

        $counts = self::counts($user);
        $tabs = collect($definition['tabs'])
            ->filter(fn (array $tab) => Route::has($tab['route']))
            ->map(fn (array $tab) => [
                'label' => $tab['label'],
                'href' => route($tab['route']),
                'count' => isset($tab['badge']) ? ($counts[$tab['badge']] ?? 0) : null,
            ]);

        return ['label' => $definition['label'], 'href' => $tabs->first()['href'] ?? '#', 'tabs' => $tabs->all()];
    }

    /**
     * Jumlah yang menunggu, sesuai cakupan data peran. Sekali per request.
     * - reports: laporan menunggu verifikasi (badge menu Laporan = tab Perlu diverifikasi).
     * - profiles: "Perubahan menunggu verifikasi" saja (badge menu Kelompok; tidak tampil bila 0).
     * - profiles_new: "Profil belum pernah diverifikasi" (tugas Dasbor).
     * - profile_queue: total keduanya = isi tab Verifikasi profil.
     *
     * @return array{reports: int, profiles: int, profiles_new: int, profile_queue: int}
     */
    public static function counts(User $user): array
    {
        if (! $user->isAdmin() && ! $user->isOpd()) {
            return ['reports' => 0, 'profiles' => 0, 'profiles_new' => 0, 'profile_queue' => 0];
        }

        $attributes = request()->attributes;
        $key = 'navigation.counts.'.$user->id;

        if (! $attributes->has($key)) {
            $profiles = Organization::profileReviewQueue($user)->toBase()
                ->selectRaw('organizations.profile_status as status, COUNT(*) as total')
                ->groupBy('organizations.profile_status')
                ->pluck('total', 'status');
            $changed = (int) ($profiles[ProfileStatus::NeedsReview->value] ?? 0);
            $new = (int) ($profiles[ProfileStatus::Unverified->value] ?? 0);

            $attributes->set($key, [
                'reports' => MonthlyReport::verificationQueue($user)->count(),
                'profiles' => $changed,
                'profiles_new' => $new,
                'profile_queue' => $changed + $new,
            ]);
        }

        return $attributes->get($key);
    }

    /** $group: menu sidebar dikelompokkan tanpa judul (garis pemisah antar-grup). */
    private static function section(string $label, string $icon, array $tabs, array $active, ?string $short = null, bool $primary = true, ?string $badge = null, int $group = 1): array
    {
        return compact('label', 'short', 'icon', 'primary', 'badge', 'active', 'tabs', 'group');
    }

    private static function item(string $label, string $route, string $icon, ?string $short = null, bool $primary = false, ?string $active = null): array
    {
        return [
            'label' => $label,
            'short' => $short,
            'route' => $route,
            'icon' => $icon,
            'primary' => $primary,
            'active' => [$active ?? $route],
            'badge' => null,
            'sidebar' => true,
            'group' => 1,
        ];
    }
}
