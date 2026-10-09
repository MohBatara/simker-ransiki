{{-- Judul bagian di latar terang, dengan tautan opsional di kanan. --}}
<div class="flex flex-wrap items-end justify-between gap-3">
    <h2 class="text-page-title font-semibold text-ink">{{ $title }}</h2>
    @isset($link)
        <a href="{{ $link['href'] }}" class="rounded-control text-ui font-semibold text-brand hover:text-brand-hover">{{ $link['label'] }}</a>
    @endisset
</div>
