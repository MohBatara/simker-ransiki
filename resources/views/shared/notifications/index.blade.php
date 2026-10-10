{{-- Notifikasi akun yang login, terbaru di atas. Variabel: $notifications (sudah berisi teks), $unreadCount. --}}
@use('App\Support\Format')

<x-layouts.app
    title="Notifikasi"
    :description="$unreadCount ? $unreadCount.' belum dibaca.' : 'Semua sudah dibaca.'"
>
    @if ($unreadCount > 0)
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <x-button type="submit" variant="secondary" icon="check">Tandai semua dibaca</x-button>
            </form>
        </x-slot:actions>
    @endif

    @if ($notifications->isEmpty())
        <x-empty-state title="Belum ada notifikasi" description="Pemberitahuan tentang laporan akan muncul di sini." />
    @else
        <x-card flush>
            <ul class="divide-y divide-line">
                @foreach ($notifications as $notification)
                    <li>
                        <a href="{{ route('notifications.open', $notification['id']) }}" class="flex gap-3 px-4 py-3.5 transition-colors hover:bg-subtle md:px-5">
                            <span @class(['mt-2 size-2 shrink-0 rounded-full', 'bg-brand' => $notification['unread']]) aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span @class(['block', 'font-semibold text-ink' => $notification['unread'], 'text-ink-soft' => ! $notification['unread']])>
                                    @if ($notification['unread'])<span class="sr-only">Belum dibaca: </span>@endif{{ $notification['text'] }}
                                </span>
                                @if ($notification['note'])
                                    <span class="mt-0.5 line-clamp-2 block text-ink-soft">“{{ $notification['note'] }}”</span>
                                @endif
                                <span class="mt-1 block text-caption text-ink-muted">{{ Format::relativeDateTime($notification['created_at']) }}</span>
                            </span>
                            <x-heroicon-o-chevron-right class="mt-1 size-4 shrink-0 text-ink-muted" />
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($notifications->hasPages())
                <x-slot:footer>{{ $notifications->links() }}</x-slot:footer>
            @endif
        </x-card>
    @endif
</x-layouts.app>
