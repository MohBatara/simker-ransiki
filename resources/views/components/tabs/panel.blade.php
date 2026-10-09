@props([
    'name',
])

<div role="tabpanel" x-cloak x-show="tab === @js($name)" {{ $attributes }}>
    {{ $slot }}
</div>
