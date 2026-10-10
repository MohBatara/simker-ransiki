@props([
    // list of ['label' => 'Terverifikasi', 'value' => 12, 'tone' => ok|wait|fix|idle|empty, 'href' => ?string]
    'segments' => [],
    // Judul untuk pembaca layar, mis. "Pelaporan September 2026".
    'label' => '',
    'size' => 'md',
    'legend' => true,
])

{{--
    Bilah bertumpuk untuk status (warna status hanya di sini, sesuai Bagian 7). Legenda berisi angka
    yang bisa diklik. "empty" = belum ada laporan, ditampilkan abu netral.
--}}
@php
    $segments = collect($segments);
    $total = $segments->sum('value');
    $fill = fn (string $tone) => match ($tone) {
        'ok' => 'bg-ok',
        'wait' => 'bg-wait',
        'fix' => 'bg-fix',
        'idle' => 'bg-idle',
        default => 'bg-line-strong',
    };
    $summary = $segments->map(fn ($segment) => $segment['value'].' '.mb_strtolower($segment['label']))->join(', ');
@endphp

<div {{ $attributes->class('space-y-3') }}>
    <div
        role="img"
        aria-label="{{ $label }}: {{ $total ? $summary : 'tidak ada data' }}"
        @class(['flex w-full overflow-hidden rounded-full bg-subtle', 'h-3' => $size === 'md', 'h-2' => $size === 'sm'])
    >
        @foreach ($segments as $segment)
            @if ($total > 0 && $segment['value'] > 0)
                <span class="{{ $fill($segment['tone']) }} h-full border-r-2 border-paper last:border-r-0" style="width: {{ round($segment['value'] / $total * 100, 2) }}%"></span>
            @endif
        @endforeach
    </div>

    @if ($legend)
        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-ui">
            @foreach ($segments as $segment)
                <li>
                    @php
                        $content = '<span class="size-2.5 shrink-0 rounded-full '.$fill($segment['tone']).'" aria-hidden="true"></span>'
                            .'<span class="text-ink-soft">'.e($segment['label']).'</span>'
                            .'<span class="font-semibold tabular-nums text-ink">'.e(\App\Support\Format::number($segment['value'])).'</span>';
                    @endphp
                    @if ($segment['href'] ?? null)
                        <a href="{{ $segment['href'] }}" class="inline-flex min-h-11 items-center gap-2 rounded-control hover:underline md:min-h-0">{!! $content !!}</a>
                    @else
                        <span class="inline-flex items-center gap-2">{!! $content !!}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
