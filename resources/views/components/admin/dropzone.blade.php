@props([
    'name',
    'accept' => 'image/jpeg,image/png',
    // Batas ukuran dan tipe, selalu tertulis di kotak.
    'hint' => 'JPG atau PNG, maksimal 10 MB.',
    // URL gambar saat ini (pratinjau awal); null = belum ada.
    'current' => null,
    // false: tanpa pratinjau di kotak (halaman punya pratinjau sendiri, mis. slide hero).
    'preview' => true,
])

@php
    $id = \App\Support\FormField::id($name);
@endphp

{{--
    Unggah satu file lewat form biasa: kotak tarik-dan-lepas (atau klik/Enter untuk memilih) dengan pratinjau gambar, nama
    dan ukuran file, serta batas ukuran tertulis. Atribut tambahan (mis. x-on:change) diteruskan ke <input type="file">.
--}}
<div x-data="dropzone(@js($current))" x-on:dragover.prevent="over = true" x-on:dragleave.prevent="over = false" x-on:drop.prevent="drop($event)" class="space-y-3">
    <label
        for="{{ $id }}"
        x-bind:class="over && 'border-brand! bg-brand-tint!'"
        class="flex cursor-pointer flex-col items-center gap-2 rounded-tile border border-dashed border-line-input bg-paper px-4 py-6 text-center transition-colors hover:bg-subtle has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand"
    >
        <span class="flex size-10 items-center justify-center rounded-full bg-brand-tint text-brand" aria-hidden="true"><x-heroicon-o-arrow-up-tray class="size-5" /></span>
        <span class="text-ui text-ink"><span class="font-semibold text-brand">Pilih file</span> <span class="max-md:hidden">atau tarik dan lepas di sini</span></span>
        <span class="text-caption text-ink-muted">{{ $hint }}</span>
        <input type="file" id="{{ $id }}" name="{{ $name }}" accept="{{ $accept }}" x-ref="input" x-on:change="changed($event)" class="sr-only" {{ $attributes }}>
    </label>

    <div x-cloak x-show="name || ({{ $preview ? 'true' : 'false' }} && preview)" class="flex items-center gap-3 rounded-control border border-line bg-paper p-2">
        @if ($preview)
            <template x-if="preview"><img x-bind:src="preview" alt="" class="size-14 shrink-0 rounded-control bg-subtle object-cover"></template>
        @endif
        <div class="min-w-0 flex-1 text-ui">
            <p class="truncate font-semibold text-ink" x-text="name || 'Foto saat ini'"></p>
            <p x-show="size" class="text-caption text-ink-muted" x-text="size"></p>
        </div>
        <button type="button" x-show="name" x-on:click="clear()" class="flex size-11 shrink-0 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink md:size-9" aria-label="Batalkan pilihan file">
            <x-heroicon-o-x-mark class="size-5" />
        </button>
    </div>
</div>
