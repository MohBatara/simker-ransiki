{{--
    Kartu berita publik. Variabel: $post (kolom publik), $compact (opsional: tanpa ringkasan, untuk beranda).
    Tanpa foto: kartu teks saja, tanpa petak pengganti.
--}}
@use('App\Support\Format')

@php
    $url = route('public.post', $post->slug);
    $sources = $post->coverSources();
    $compact ??= false;
@endphp

<article class="flex h-full flex-col overflow-hidden rounded-public border border-line bg-paper shadow-card">
    @if ($sources)
        <a href="{{ $url }}" @class(['block overflow-hidden bg-subtle', 'aspect-[4/3]' => $compact, 'aspect-[16/9]' => ! $compact]) tabindex="-1" aria-hidden="true">
            <x-public.picture :image="$sources" alt="" :sizes="$compact ? '(min-width: 64rem) 13rem, (min-width: 48rem) 33vw, 70vw' : '(min-width: 64rem) 22rem, (min-width: 40rem) 50vw, 100vw'" width="480" :height="$compact ? 360 : 270" frame="block size-full" class="size-full object-cover" />
        </a>
    @endif
    <div class="flex flex-1 flex-col items-start gap-2 p-4">
        <x-public.pill>{{ $post->category->label() }}</x-public.pill>
        <h3 @class(['font-serif font-semibold', 'text-body line-clamp-3' => $compact, 'text-card-title' => ! $compact])><a href="{{ $url }}" class="rounded-control text-ink hover:text-brand">{{ $post->title }}</a></h3>
        <p @class(['text-caption text-ink-muted', 'mt-auto' => $compact])><time datetime="{{ $post->published_at->toAtomString() }}">{{ Format::date($post->published_at) }}</time></p>
        @if (! $compact && ($text = $post->summary ?: Format::excerpt($post->body, 160)))
            <p class="text-ui text-ink-soft">{{ $text }}</p>
        @endif
    </div>
</article>
