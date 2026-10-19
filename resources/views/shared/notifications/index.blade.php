{{-- Notifikasi akun yang login, terbaru di atas. Variabel: $notifications (sudah berisi teks), $unreadCount. --}}
@use('App\Support\Format')

<x-layouts.app
    title="Notifikasi"
    :description="$notifications->isEmpty() ? null : ($unreadCount ? $unreadCount.' belum dibaca.' : 'Semua sudah dibaca.')"
>
    @if ($unreadCount > 0)
        <x-slot:actions>
            <form novalidate method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <x-button type="submit" variant="secondary" icon="check">Tandai semua dibaca</x-button>
            </form>
        </x-slot:actions>
    @endif

    @if ($notifications->isEmpty())
        <x-admin.empty icon="bell" title="Belum ada notifikasi" description="Pemberitahuan tentang laporan akan muncul di sini." />
    @else
        <x-admin.card flush>
            <x-list flush>
                @foreach ($notifications as $notification)
                    <x-list.item :href="route('notifications.open', $notification['id'])" class="items-start">
                        <span @class(['mt-2 size-2 shrink-0 rounded-full', 'bg-brand' => $notification['unread']]) aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block', 'font-semibold text-ink' => $notification['unread'], 'text-ink-soft' => ! $notification['unread']])>
                                @if ($notification['unread'])<span class="sr-only">Belum dibaca: </span>@endif{{ $notification['text'] }}
                            </span>
                            @if ($notification['note'])
                                <span class="mt-0.5 line-clamp-2 block break-words text-ink-soft">“{{ $notification['note'] }}”</span>
                            @endif
                            <span class="mt-1 block text-caption text-ink-muted">{{ \App\Support\Format::orEmpty(Format::relativeDateTime($notification['created_at'])) }}</span>
                        </span>
                    </x-list.item>
                @endforeach
            </x-list>
            @if ($notifications->hasPages())
                <x-slot:footer><x-admin.pagination :paginator="$notifications" noun="notifikasi" class="w-full" /></x-slot:footer>
            @endif
        </x-admin.card>
    @endif
</x-layouts.app>
