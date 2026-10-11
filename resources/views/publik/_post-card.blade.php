{{-- Kartu berita publik. Variabel: $post (kolom publik). Tanpa foto: kartu teks saja, tanpa petak pengganti. --}}
@use('App\Support\Format')

@php
    $url = route('public.post', $post->slug);
    $sources = $post->coverSources();
@endphp

<article class="flex flex-col overflow-hidden rounded-card border border-line bg-paper">
    @if ($sources)
        <a href="{{ $url }}" class="block aspect-[16/9] bg-subtle" tabindex="-1" aria-hidden="true">
            <x-public.picture :image="$sources" alt="" sizes="(min-width: 64rem) 22rem, (min-width: 40rem) 50vw, 100vw" width="480" height="270" frame="block size-full" class="size-full object-cover" />
        </a>
    @endif
    <div class="flex flex-1 flex-col items-start gap-2 p-4">
        <x-public.pill>{{ $post->category->label() }}</x-public.pill>
        <h3 class="text-card-title font-semibold"><a href="{{ $url }}" class="rounded-control text-ink hover:text-brand">{{ $post->title }}</a></h3>
        <p class="text-caption text-ink-muted"><time datetime="{{ $post->published_at->toAtomString() }}">{{ Format::date($post->published_at) }}</time></p>
        @if ($text = $post->summary ?: Format::excerpt($post->body, 160))
            <p class="text-ui text-ink-soft">{{ $text }}</p>
        @endif
    </div>
</article>
