<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use App\Support\NotificationText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Notifikasi milik akun yang login (semua peran). Tujuan klik dihitung ulang dan dicek Policy,
 * sehingga notifikasi tidak pernah memberi akses tambahan.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(20);
        $notifications->getCollection()->transform(fn (DatabaseNotification $notification) => [
            'id' => $notification->id,
            'unread' => $notification->read_at === null,
            'created_at' => $notification->created_at,
            ...NotificationText::describe($notification),
        ]);

        return view('shared.notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /** Ditandai dibaca lalu diarahkan ke tujuannya (GET: tautan biasa, efek sampingnya hanya "dibaca"). */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $target = $this->target($request->user(), $notification);

        return $target
            ? redirect()->to($target)
            : redirect()->route('notifications.index')->with('error', 'Laporan ini tidak bisa dibuka lagi dari akun Anda.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('status', 'Semua notifikasi ditandai dibaca.');
    }

    private function target(User $user, DatabaseNotification $notification): ?string
    {
        $data = $notification->data;

        if (($data['kind'] ?? null) === 'possible_duplicate') {
            return $user->isAdmin() ? route('admin.organizations.duplicates.index') : null;
        }

        if (($data['kind'] ?? null) === 'report_reminder') {
            return $user->isKelompok() ? route('kelompok.reports.index') : null;
        }

        if (($data['kind'] ?? null) === 'profile_needs_review') {
            $organization = Organization::find($data['organization_id'] ?? null);

            return $organization && Gate::forUser($user)->allows('viewHistory', $organization)
                ? route($user->role->area().'.organizations.history', $organization)
                : null;
        }

        if (($data['kind'] ?? null) === 'report_overdue') {
            if ($user->isKelompok()) {
                return route('kelompok.reports.index');
            }
            $organization = Organization::find($data['organization_id'] ?? null);

            return $organization && Gate::forUser($user)->allows('view', $organization)
                ? route($user->role->area().'.organizations.reports', $organization)
                : null;
        }

        $report = isset($data['monthly_report_id']) ? MonthlyReport::find($data['monthly_report_id']) : null;

        if (! $report || Gate::forUser($user)->denies('view', $report)) {
            return null;
        }

        if ($user->isKelompok()) {
            return $user->can('update', $report) ? route('kelompok.reports.edit', $report) : route('kelompok.reports.show', $report);
        }

        return route($user->role->area().'.reports.show', $report);
    }
}
