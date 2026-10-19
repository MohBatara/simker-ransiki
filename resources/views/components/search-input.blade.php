@props([
    'name' => 'q',
    'value' => null,
    'label' => 'Cari',
    // id kotak cari bila ada dua kotak bernama sama di satu halaman (mis. cari global di kepala halaman).
    'id' => null,
    // false bila sudah ada label terlihat di luar (mis. di dalam x-field).
    'withLabel' => true,
    // Teks petunjuk bila berbeda dari label (mis. kotak cari ringkas "Cari").
    'placeholder' => null,
])

{{-- Kotak cari dengan ikon kaca pembesar; Enter mengirim form. Label tersedia untuk pembaca layar. --}}
<div {{ $attributes->class('relative min-w-0') }}>
    @if ($withLabel)
        <label for="{{ $id ?? $name }}" class="sr-only">{{ $label }}</label>
    @endif
    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-ink-muted app:size-4" aria-hidden="true" />
    <x-input type="search" :name="$name" :id="$id" :value="$value" :placeholder="$placeholder ?? $label" enterkeyhint="search" autocomplete="off" class="pl-10! app:pl-9!" />
</div>
