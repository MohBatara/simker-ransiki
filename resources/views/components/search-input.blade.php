@props([
    'name' => 'q',
    'value' => null,
    'label' => 'Cari',
])

{{-- Kotak cari dengan ikon kaca pembesar; Enter mengirim form. Label tersedia untuk pembaca layar. --}}
<div {{ $attributes->class('relative min-w-0') }}>
    <label for="{{ $name }}" class="sr-only">{{ $label }}</label>
    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-ink-muted" aria-hidden="true" />
    <x-input type="search" :name="$name" :value="$value" :placeholder="$label" enterkeyhint="search" autocomplete="off" class="pl-10!" />
</div>
