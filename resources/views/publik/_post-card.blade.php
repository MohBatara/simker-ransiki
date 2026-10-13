{{--
    Kartu berita (halaman Berita dan "Berita lainnya"). Variabel: $post (kolom publik), $level (opsional, h2/h3).
    Foto 16:10 atau panel cream berikon kategori setinggi foto, sehingga baris meta semua kartu sejajar.
    Seluruh kartu dapat diklik lewat satu tautan di judul; cincin fokus mengelilingi kartu.
--}}
@php
    $sources = $post->coverSources();
    $level ??= 'h3';
@endphp

<article class="group relative flex h-full flex-col has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-4 has-[a:focus-visible]:outline-brand">
    <div class="aspect-[16/10] overflow-hidden rounded-tile bg-cream">
        @if ($sources)
            <x-public.picture :image="$sources" alt="" sizes="(min-width: 64rem) 22rem, (min-width: 48rem) 50vw, 100vw" width="480" height="300" frame="block size-full" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100" />
        @else
            <span class="flex size-full items-center justify-center text-clay" aria-hidden="true">
                <x-dynamic-component :component="'heroicon-o-'.$post->category->icon()" class="size-12" />
            </span>
        @endif
    </div>
    @include('publik.posts._meta', ['post' => $post, 'class' => 'mt-4'])
    <{{ $level }} class="mt-2 line-clamp-3 font-serif text-title-sm font-semibold"><a href="{{ route('public.post', $post->slug) }}" class="text-ink after:absolute after:inset-0 group-hover:text-brand focus-visible:outline-none">{{ $post->title }}</a></{{ $level }}>
    @if ($text = $post->summary ?: App\Support\Format::excerpt($post->body, 160))
        <p class="mt-2 line-clamp-2 text-ui text-ink-soft">{{ $text }}</p>
    @endif
</article>
