@props([
    'name',
    'multiple' => false,
    'accept' => '.jpg,.jpeg,.png,.pdf',
    'maxSize' => 10,
    'maxFiles' => 10,
    'hint' => null,
    'id' => null,
])

@php
    $key = \App\Support\FormField::key($name);
    $id ??= \App\Support\FormField::id($name);
    $hint ??= 'JPG, PNG, atau PDF. Maksimal '.$maxSize.' MB per file'.($multiple ? ', '.$maxFiles.' file.' : '.');
    $fileError = $errors->first($key.'.*');
@endphp

<div
    x-data="fileUpload(@js(['maxMb' => $maxSize, 'maxFiles' => $maxFiles, 'multiple' => $multiple]))"
    {{ $attributes->class('space-y-2') }}
>
    <label
        for="{{ $id }}"
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="drop($event)"
        x-bind:class="dragging && 'border-brand! bg-brand-tint!'"
        class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-control border border-dashed border-line-input bg-paper px-4 py-6 text-center transition-colors hover:bg-subtle"
    >
        <span class="text-ui">
            <span class="font-semibold text-brand">Pilih file</span>
            <span class="text-ink-soft">atau seret ke sini</span>
        </span>
        <span class="text-caption text-ink-muted">{{ $hint }}</span>
        <input
            type="file"
            id="{{ $id }}"
            name="{{ $multiple ? $key.'[]' : $key }}"
            accept="{{ $accept }}"
            x-ref="input"
            x-on:change="sync()"
            @if ($multiple) multiple @endif
            class="sr-only"
        >
    </label>

    <ul x-cloak x-show="files.length" class="divide-y divide-line rounded-control border border-line bg-paper">
        <template x-for="file in files" :key="file.name">
            <li class="flex items-center justify-between gap-3 px-3 py-2 text-ui">
                <span class="truncate" x-text="file.name"></span>
                <span
                    class="shrink-0 tabular-nums"
                    x-bind:class="file.tooBig ? 'text-fix' : 'text-ink-muted'"
                    x-text="file.tooBig ? 'Lebih dari ' + maxMb + ' MB' : formatSize(file.size)"
                ></span>
            </li>
        </template>
    </ul>

    <p x-cloak x-show="tooMany" class="text-caption text-fix">
        Terlalu banyak file. Pilih paling banyak <span x-text="maxFiles"></span> file.
    </p>

    @if ($fileError)
        <p class="text-caption text-fix">{{ $fileError }}</p>
    @endif
</div>
