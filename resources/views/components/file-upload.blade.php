@props([
    'url' => '',
    // 'document' = JPG/PNG/PDF (lampiran), 'photo' = JPG/PNG (foto produk).
    'kind' => 'document',
    'multiple' => true,
    // Sisa slot file untuk entitas ini (maks 10 per entitas).
    'remaining' => 10,
    // Tampilkan tombol "Ambil foto" yang langsung membuka kamera belakang di HP.
    'camera' => false,
    'hint' => null,
])

@php
    // Hanya JPEG/PNG agar iPhone otomatis mengonversi foto HEIC menjadi JPEG.
    $accept = $kind === 'photo' ? 'image/jpeg,image/png' : 'image/jpeg,image/png,application/pdf';
    $hint ??= ($kind === 'photo' ? 'Foto JPG atau PNG.' : 'JPG, PNG, atau PDF.').' Maksimal 10 MB per file.';
@endphp

<div
    x-data="uploader(@js(['kind' => $kind, 'maxMb' => 10, 'remaining' => $remaining, 'multiple' => $multiple]))"
    data-url="{{ $url }}"
    {{ $attributes->class('space-y-3') }}
>
    <div
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="drop($event)"
        x-bind:class="dragging && 'border-brand! bg-brand-tint!'"
        class="flex flex-col items-center gap-3 rounded-control border border-dashed border-line-input bg-paper px-4 py-5 text-center transition-colors"
    >
        <div class="flex flex-wrap justify-center gap-2">
            @if ($camera)
                <x-button variant="secondary" icon="camera" x-on:click="$refs.camera.click()">Ambil foto</x-button>
            @endif
            <x-button variant="secondary" icon="arrow-up-tray" x-on:click="$refs.picker.click()">Pilih file</x-button>
        </div>
        <p class="text-caption text-ink-muted">{{ $hint }} <span class="max-md:hidden">Bisa juga diseret ke sini.</span></p>
    </div>

    <input type="file" x-ref="picker" class="sr-only" tabindex="-1" aria-hidden="true" accept="{{ $accept }}" @if ($multiple) multiple @endif x-on:change="add($event.target.files); $event.target.value = ''">
    @if ($camera)
        <input type="file" x-ref="camera" class="sr-only" tabindex="-1" aria-hidden="true" accept="image/jpeg,image/png" capture="environment" x-on:change="add($event.target.files); $event.target.value = ''">
    @endif

    <ul x-cloak x-show="queue.length" class="divide-y divide-line rounded-control border border-line bg-paper" aria-live="polite">
        <template x-for="item in queue" :key="item.key">
            <li class="space-y-1.5 px-3 py-2.5">
                <div class="flex items-center justify-between gap-3 text-ui">
                    <span class="truncate" x-text="item.name"></span>
                    <span class="shrink-0 text-caption tabular-nums" x-bind:class="item.status === 'error' ? 'text-fix' : 'text-ink-muted'" x-text="statusText(item)"></span>
                </div>
                <div x-show="item.status === 'uploading' || item.status === 'preparing'" class="h-1.5 overflow-hidden rounded-full bg-subtle" role="progressbar" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="item.progress">
                    <div class="h-full bg-brand transition-[width]" x-bind:style="`width: ${item.progress}%`"></div>
                </div>
                <div x-show="item.status === 'error'" class="flex items-start justify-between gap-3">
                    <p class="text-caption text-fix" x-text="item.error"></p>
                    <x-link x-show="item.retryable" x-on:click="retry(item)" class="shrink-0 text-caption">Coba lagi</x-link>
                </div>
            </li>
        </template>
    </ul>

    <div x-cloak x-show="needsReload" class="flex justify-end">
        <x-button variant="secondary" x-on:click="window.location.reload()">Muat ulang daftar file</x-button>
    </div>
</div>
