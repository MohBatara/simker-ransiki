@props([
    // "lucide:nama" atau "heroicon:nama" dari App\Support\PublicIcons.
    'name',
])

@php
    [$set, $icon] = explode(':', $name, 2);
@endphp

@if ($set === 'lucide')
    {{-- Garis 1,5 agar setebal Heroicons outline. --}}
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>{!! App\Support\PublicIcons::lucide($icon) !!}</svg>
@else
    <x-dynamic-component :component="'heroicon-o-'.$icon" aria-hidden="true" {{ $attributes }} />
@endif
