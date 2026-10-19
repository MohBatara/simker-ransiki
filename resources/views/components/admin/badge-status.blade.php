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
        'info' => 'bg-info-bg text-info',
        'danger' => 'bg-fix-bg text-fix',
        default => 'bg-idle-bg text-idle',
    };
@endphp

{{-- Pil status lembut (sudut 6px, teks 12px medium, tanpa titik). SATU peta warna: App\Support\StatusTone. --}}
<span data-tone="{{ $tone }}" {{ $attributes->class(['inline-flex h-6 items-center whitespace-nowrap rounded-pill px-2 text-label font-medium', $classes]) }}>{{ $label ?? $slot }}</span>
