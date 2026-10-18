@props([
    // Enum status (ReportStatus, OrganizationStatus, LicenseStatus, ...); warnanya dari App\Support\StatusTone.
    'status' => null,
    // Timpa label/warna untuk penanda di luar enum (mis. "Terlambat" danger, "Diajukan ulang" warning).
    'label' => null,
    'tone' => null,
])

@php
    $tone ??= $status ? \App\Support\StatusTone::of($status) : 'neutral';
    $label ??= $status?->label();
    $classes = match ($tone) {
        'success' => 'bg-ok-bg text-ok',
        'warning' => 'bg-wait-bg text-wait',
        'orange' => 'bg-orange-bg text-orange',
        'danger' => 'bg-fix-bg text-fix',
        default => 'bg-idle-bg text-idle',
    };
@endphp

{{-- Pil status dengan titik warna di depan teks. SATU peta warna: App\Support\StatusTone. --}}
<span data-tone="{{ $tone }}" {{ $attributes->class(['inline-flex h-6 items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-caption font-semibold', $classes]) }}><span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>{{ $label ?? $slot }}</span>
