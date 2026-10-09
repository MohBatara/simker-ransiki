@props([
    'tone' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    [$surface, $iconColor, $icon] = match ($tone) {
        'ok' => ['bg-ok-bg', 'text-ok', 'check-circle'],
        'wait' => ['bg-wait-bg', 'text-wait', 'exclamation-triangle'],
        'fix' => ['bg-fix-bg', 'text-fix', 'exclamation-circle'],
        default => ['bg-brand-tint', 'text-brand', 'information-circle'],
    };
@endphp

<div
    role="{{ $tone === 'fix' ? 'alert' : 'status' }}"
    @if ($dismissible) x-data="{ show: true }" x-show="show" @endif
    {{ $attributes->class(['flex gap-3 rounded-card p-4 text-ui text-ink', $surface]) }}
>
    <x-dynamic-component :component="'heroicon-o-'.$icon" @class(['size-5 shrink-0', $iconColor]) />
    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-0.5' => $title])>{{ $slot }}</div>
    </div>
    @if ($dismissible)
        <button type="button" x-on:click="show = false" class="-m-1 h-fit rounded-control p-1 text-ink-soft hover:text-ink" aria-label="Tutup">
            <x-heroicon-o-x-mark class="size-5" />
        </button>
    @endif
</div>
