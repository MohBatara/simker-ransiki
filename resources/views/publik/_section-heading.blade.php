{{-- Judul bagian bergaya buletin: garis tegas di atas, judul, dan tautan opsional di kanan. --}}
<div class="flex flex-wrap items-baseline justify-between gap-3 border-t border-line-strong pt-4">
    <h2 class="text-card-title font-semibold text-ink">{{ $title }}</h2>
    @isset($link)
        <a href="{{ $link['href'] }}" class="rounded-control text-ui text-brand hover:text-brand-hover">{{ $link['label'] }}</a>
    @endisset
</div>
