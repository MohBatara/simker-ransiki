{{--
    Toast seragam di pojok kanan bawah (di HP di atas navigasi bawah), menggantikan alert pesan sistem: sukses (status),
    peringatan (warning), gagal (error). Hilang otomatis setelah 4 detik, berhenti saat disorot/difokuskan, bisa ditutup.
    Pesan dari JS: window.dispatchEvent(new CustomEvent('toast', { detail: { tone: 'ok', message: '...' } })).
--}}
@php
    $messages = collect([
        ['tone' => 'ok', 'message' => session('status')],
        ['tone' => 'wait', 'message' => session('warning')],
        ['tone' => 'fix', 'message' => session('error')],
    ])->filter(fn ($toast) => filled($toast['message']))->values();
@endphp

<div
    x-data="toasts(@js($messages))"
    x-on:toast.window="push($event.detail)"
    class="pointer-events-none fixed right-4 bottom-[calc(var(--app-bottom,0px)+1rem)] left-4 z-50 flex flex-col items-end gap-2 md:left-auto md:w-96 print:hidden"
>
    {{-- Tanpa JS pesan tetap terbaca. --}}
    <noscript>
        @foreach ($messages as $toast)
            <p class="pointer-events-auto rounded-tile border border-line bg-paper px-4 py-3 text-ui text-ink shadow-float">{{ $toast['message'] }}</p>
        @endforeach
    </noscript>
    <template x-for="toast in items" x-bind:key="toast.id">
        <div
            x-show="toast.visible"
            x-transition.opacity.duration.150ms
            x-on:mouseenter="pause(toast)"
            x-on:mouseleave="resume(toast)"
            x-on:focusin="pause(toast)"
            x-on:focusout="resume(toast)"
            x-bind:role="toast.tone === 'fix' ? 'alert' : 'status'"
            class="pointer-events-auto flex w-full items-start gap-3 rounded-tile border border-line bg-paper px-4 py-3 text-ui text-ink shadow-float"
            data-toast
        >
            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full" x-bind:class="{ 'bg-ok-bg text-ok': toast.tone === 'ok', 'bg-wait-bg text-wait': toast.tone === 'wait', 'bg-fix-bg text-fix': toast.tone === 'fix' }" aria-hidden="true">
                <x-heroicon-o-check class="size-3.5" x-show="toast.tone === 'ok'" />
                <x-heroicon-o-exclamation-triangle class="size-3.5" x-show="toast.tone === 'wait'" />
                <x-heroicon-o-exclamation-circle class="size-3.5" x-show="toast.tone === 'fix'" />
            </span>
            <p class="min-w-0 flex-1" x-text="toast.message"></p>
            <button type="button" x-on:click="dismiss(toast)" class="-my-2 -mr-2 flex size-9 shrink-0 items-center justify-center rounded-control text-ink-muted hover:bg-subtle hover:text-ink" aria-label="Tutup">
                <x-heroicon-o-x-mark class="size-4" />
            </button>
        </div>
    </template>
    {{-- Teks pesan juga di server agar terbaca sebelum JS dimuat (dan oleh tes). --}}
    @foreach ($messages as $toast)
        <span class="sr-only" data-flash="{{ $toast['tone'] }}">{{ $toast['message'] }}</span>
    @endforeach
</div>
