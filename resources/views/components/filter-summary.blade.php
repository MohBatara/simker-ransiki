@props([
    // Ringkasan hasil, mis. "25 kelompok · 3 tidak aktif".
    'summary' => null,
    // list of ['label' => 'Jenis: KUB', 'href' => URL tanpa filter itu]
    'chips' => [],
    'clearUrl' => null,
])

{{-- Ringkasan hasil + filter aktif sebagai chip yang bisa dihapus satu per satu. --}}
<div {{ $attributes->class('flex flex-wrap items-center gap-x-3 gap-y-2') }}>
    @if ($summary)
        <p class="text-ui text-ink-soft tabular-nums" role="status">{{ $summary }}</p>
    @endif
    @if ($chips)
        <ul class="flex flex-wrap items-center gap-2" aria-label="Filter aktif">
            @foreach ($chips as $chip)
                <li>
                    <a href="{{ $chip['href'] }}" class="inline-flex h-11 items-center gap-1 rounded-full border border-line-strong bg-paper pl-3 pr-2 text-caption text-ink transition-colors hover:bg-subtle md:h-8" aria-label="Hapus filter {{ $chip['label'] }}">
                        {{ $chip['label'] }}
                        <x-heroicon-o-x-mark class="size-4 text-ink-muted" aria-hidden="true" />
                    </a>
                </li>
            @endforeach
            @if ($clearUrl && count($chips) > 1)
                <li><x-link :href="$clearUrl" muted class="text-caption">Hapus semua</x-link></li>
            @endif
        </ul>
    @endif
</div>
