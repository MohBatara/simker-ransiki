@props([
    'name',
    // Nama instansi/OPD/kelompok di samping tanggal (opsional).
    'subtitle' => null,
    // Angka terpenting di kartu mini kiri: ['value' => ..., 'label' => ..., 'icon' => ..., 'href' => ?string].
    'highlight' => null,
    // Satu kalimat ringkas di bawah sapaan.
    'sentence' => null,
    // Tautan aksi brand bergaris bawah: ['label' => ..., 'href' => ...].
    'action' => null,
])

@php
    $now = now(config('simker.reports.timezone'));
    $greeting = match (true) {
        $now->hour < 11 => 'Selamat pagi',
        $now->hour < 15 => 'Selamat siang',
        $now->hour < 18 => 'Selamat sore',
        default => 'Selamat malam',
    };
    $firstName = \Illuminate\Support\Str::of($name)->before(' ')->toString() ?: $name;
@endphp

{{--
    Banner sapaan dasbor selebar penuh: gradasi lembut brand-50 ke brand-mist dengan bingkai brand-100 (atas permintaan,
    pengecualian larangan gradasi). Kiri: kartu mini putih berisi angka terpenting; tengah: sapaan menurut jam WIT,
    tanggal, satu kalimat, tautan aksi; kanan: slot (pemilih periode). Teks ink/ink-2/brand di atas brand-50 ≥ 4,9:1.
--}}
<section {{ $attributes->class('flex flex-col gap-4 rounded-box border border-brand-100 bg-linear-to-r from-brand-50 to-brand-mist p-4 md:flex-row md:items-center md:gap-6 md:p-5') }}>
    @if ($highlight)
        @php $tag = ($highlight['href'] ?? null) ? 'a' : 'div'; @endphp
        <{{ $tag }} @if ($highlight['href'] ?? null) href="{{ $highlight['href'] }}" @endif class="flex shrink-0 items-center gap-3 rounded-box border border-line bg-panel px-4 py-3 shadow-xs md:w-60 {{ ($highlight['href'] ?? null) ? 'transition-colors hover:border-brand-100' : '' }}">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-control bg-brand-50 text-brand" aria-hidden="true">
                <x-dynamic-component :component="'heroicon-o-'.($highlight['icon'] ?? 'clipboard-document-check')" class="size-5" />
            </span>
            <span class="min-w-0">
                <span class="block text-[1.375rem] leading-7 font-semibold tabular-nums text-ink">{{ $highlight['value'] }}</span>
                <span class="block text-label text-ink-2">{{ $highlight['label'] }}</span>
            </span>
        </{{ $tag }}>
    @endif
    <div class="min-w-0 flex-1">
        <p class="text-label text-ink-2"><time datetime="{{ $now->toDateString() }}">{{ \App\Support\Format::dayDate($now) }}</time>@if ($subtitle) <span aria-hidden="true">·</span> {{ $subtitle }}@endif</p>
        <p class="mt-0.5 text-page-title font-semibold text-ink">{{ $greeting }}, {{ $firstName }}</p>
        @if ($sentence)
            <p class="mt-0.5 text-caption text-ink-2">{{ $sentence }}</p>
        @endif
        @if ($action)
            <a href="{{ $action['href'] }}" class="mt-1 inline-flex min-h-11 items-center gap-1 text-caption font-semibold text-brand underline underline-offset-4 hover:text-brand-hover md:min-h-8">{{ $action['label'] }} <x-heroicon-o-arrow-right class="size-3.5" aria-hidden="true" /></a>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="shrink-0 md:w-52">{{ $slot }}</div>
    @endif
</section>
