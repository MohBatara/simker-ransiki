@use('App\Support\Format')

@php
    $sources = $post->coverSources();
    $description = $post->summary ?: (Format::excerpt($post->body, 160) ?? 'Berita dan kegiatan Distrik Ransiki.');
@endphp

<x-layouts.public
    :title="$post->title"
    :description="$description"
    :image="$sources ? url($sources['fallback']) : null"
    type="article"
>
    <x-slot:meta>
        <meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">
    </x-slot:meta>
    <x-public.band :title="$post->title" :crumbs="['Berita' => route('public.posts'), $post->category->label() => null]">
        <x-slot:top>
            <div class="mb-4 flex flex-wrap gap-2">
                <x-public.pill tone="dark">{{ $post->category->label() }}</x-public.pill>
            </div>
        </x-slot:top>
        <p class="mt-3 text-caption text-forest-soft"><time datetime="{{ $post->published_at->toAtomString() }}">{{ Format::date($post->published_at) }}</time></p>
    </x-public.band>

    <div class="mx-auto grid max-w-public gap-10 px-4 py-10 md:px-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-14">
        <article class="min-w-0 space-y-8">
            @if ($sources)
                <x-public.picture :image="$sources" :alt="$post->title" sizes="(min-width: 64rem) 42rem, 100vw" width="960" height="540" frame="block overflow-hidden rounded-public bg-subtle max-w-[42rem]" class="w-full object-cover" />
            @endif
            @if ($post->summary)
                <p class="max-w-[42rem] text-body font-semibold text-ink">{{ $post->summary }}</p>
            @endif
            <div class="prose-public">{!! Format::markdown($post->body) !!}</div>
        </article>

        <aside class="h-fit space-y-5">
            @if ($post->organizations->isNotEmpty())
                <section class="rounded-public border border-line bg-paper shadow-card p-4 md:p-5" aria-labelledby="kelompok-terkait">
                    <h2 id="kelompok-terkait" class="font-serif text-card-title font-semibold text-ink">Kelompok terkait</h2>
                    <ul class="mt-3 space-y-2 text-ui">
                        @foreach ($post->organizations as $organization)
                            <li>
                                <a href="{{ route('public.organization', $organization->slug) }}" class="rounded-control font-semibold text-brand hover:text-brand-hover">{{ $organization->name }}</a>
                                <span class="block text-caption text-ink-muted">{{ $organization->village->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
            <a href="{{ route('public.posts') }}" class="inline-flex items-center gap-2 rounded-control text-ui font-semibold text-brand hover:text-brand-hover">
                <x-heroicon-o-arrow-left class="size-4" /> Semua berita
            </a>
        </aside>
    </div>

    @if ($others->isNotEmpty())
        <section class="mx-auto max-w-public px-4 pb-14 md:px-6">
            @include('publik._section-heading', ['title' => 'Berita lainnya'])
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($others as $other)
                    @include('publik._post-card', ['post' => $other])
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.public>
