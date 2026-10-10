@props([
    // list of ['label' => 'Modal', 'value' => 4, 'href' => ?string, 'hint' => ?string]
    'items' => [],
    // Nilai untuk bilah penuh; default nilai terbesar.
    'max' => null,
    'empty' => 'Belum ada data.',
])

{{-- Daftar label + bilah horizontal tipis (warna brand) + angka. Seluruh baris bisa diklik bila ada href. --}}
@php
    $items = collect($items);
    $max = max(1, $max ?? $items->max('value') ?? 1);
@endphp

@if ($items->isEmpty())
    <p {{ $attributes->class('text-ink-soft') }}>{{ $empty }}</p>
@else
    <x-list {{ $attributes->class('divide-y-0') }}>
        @foreach ($items as $item)
            <x-list.item :href="$item['href'] ?? null" :chevron="false" class="py-2">
                <span class="block min-w-0 flex-1">
                    <span class="flex items-baseline justify-between gap-3">
                        <span class="min-w-0 truncate text-ink">{{ $item['label'] }}</span>
                        <span class="shrink-0 font-semibold tabular-nums text-ink">{{ \App\Support\Format::number($item['value']) }}</span>
                    </span>
                    <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-subtle" aria-hidden="true">
                        <span class="block h-full rounded-full bg-brand" style="width: {{ round($item['value'] / $max * 100, 2) }}%"></span>
                    </span>
                    @if ($item['hint'] ?? null)
                        <span class="mt-0.5 block text-caption text-ink-muted">{{ $item['hint'] }}</span>
                    @endif
                </span>
            </x-list.item>
        @endforeach
    </x-list>
@endif
