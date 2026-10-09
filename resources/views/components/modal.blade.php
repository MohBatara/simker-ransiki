@props([
    'name',
    'title',
    'show' => false,
])

{{--
    Buka:  x-on:click="$dispatch('open-modal', 'nama-modal')"
    Tutup: x-on:click="$dispatch('close-modal', 'nama-modal')", tombol Esc, atau klik di luar kotak.
--}}
<dialog
    x-data
    @if ($show) x-init="$el.showModal()" @endif
    x-on:open-modal.window="if ($event.detail === @js($name)) $el.showModal()"
    x-on:close-modal.window="if ($event.detail === @js($name)) $el.close()"
    x-on:click="if ($event.target === $el) $el.close()"
    aria-labelledby="modal-{{ $name }}-title"
    {{ $attributes->class('m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-card border border-line bg-paper p-0 text-ink shadow-float backdrop:bg-ink/40') }}
>
    <div class="p-4 md:p-5">
        <div class="flex items-start justify-between gap-4">
            <h2 id="modal-{{ $name }}-title" class="text-card-title font-semibold">{{ $title }}</h2>
            <button type="button" x-on:click="$el.closest('dialog').close()" class="-m-2 rounded-control p-2 text-ink-muted hover:bg-subtle hover:text-ink" aria-label="Tutup">
                <x-heroicon-o-x-mark class="size-5" />
            </button>
        </div>
        <div class="mt-3 text-ui text-ink-soft">{{ $slot }}</div>
    </div>

    @isset($footer)
        {{-- Menempel di bawah agar tombol tetap terlihat saat isi modal lebih tinggi dari layar. --}}
        <div class="sticky bottom-0 flex flex-wrap justify-end gap-2 border-t border-line bg-paper px-4 py-3 md:px-5">{{ $footer }}</div>
    @endisset
</dialog>
