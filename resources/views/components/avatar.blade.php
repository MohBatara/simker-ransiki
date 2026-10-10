@props([
    'name',
    'size' => 'md',
])

{{-- Avatar inisial (2 huruf) untuk kelompok dan pengguna; nama tetap ditulis di sampingnya. --}}
<span aria-hidden="true" {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center rounded-full bg-subtle font-semibold text-ink-soft',
    'size-9 text-caption' => $size === 'md',
    'size-8 text-caption' => $size === 'sm',
    'size-12 text-ui' => $size === 'lg',
]) }}>{{ \App\Support\Initials::of($name) }}</span>
