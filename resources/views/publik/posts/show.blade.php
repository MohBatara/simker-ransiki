@use('App\Support\Format')

@php
    $cover = $post->coverSources();
    $url = route('public.post', $post->slug);
    $description = $post->summary ?: (Format::excerpt($post->body, 160) ?? 'Berita dan kegiatan Distrik Ransiki.');
    $image = $cover ? url($cover['fallback']) : asset('og-default.png');
    // "Diperbarui" hanya bila tanggal ubah (WIT) berbeda hari dengan tanggal terbit.
    $updated = $post->updated_at && ! $post->updated_at->isSameDay($post->published_at) && $post->updated_at->gt($post->published_at) ? $post->updated_at : null;
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => $post->title,
        'description' => $description,
        'datePublished' => $post->published_at->toAtomString(),
        'dateModified' => ($post->updated_at ?? $post->published_at)->toAtomString(),
        'image' => [$image],
        'mainEntityOfPage' => $url,
        'author' => ['@type' => 'Organization', 'name' => 'Kantor Distrik Ransiki'],
        'publisher' => ['@type' => 'Organization', 'name' => 'Kantor Distrik Ransiki', 'logo' => ['@type' => 'ImageObject', 'url' => asset('apple-touch-icon.png')]],
    ];
    $share = [
        ['WhatsApp', 'https://wa.me/?text='.rawurlencode($post->title."\n".$url), 'chat-bubble-left-right'],
        ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url), 'arrow-top-right-on-square'],
    ];
    $chip = 'inline-flex h-11 items-center gap-2 rounded-full border border-line-strong bg-paper px-4 text-ui font-semibold text-ink transition-colors hover:border-brand hover:text-brand';
@endphp

<x-layouts.public :title="$post->title" :description="$description" :image="$image" type="article">
    <x-slot:meta>
        <meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">
        <meta property="article:modified_time" content="{{ ($post->updated_at ?? $post->published_at)->toAtomString() }}">
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </x-slot:meta>

    <article data-print-article class="py-section">
        {{-- Kolom baca: maks 40rem (sekitar 68 karakter per baris pada 18px), di tengah. --}}
        <header class="mx-auto max-w-[40rem] px-4">
            <nav aria-label="Remah roti" class="text-caption text-ink-muted print:hidden">
                <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                    <li><a href="{{ route('home') }}" class="rounded-control text-ink-soft hover:text-brand hover:underline">Beranda</a></li>
                    <li class="flex items-center gap-1.5"><x-heroicon-o-chevron-right class="size-3.5" aria-hidden="true" /><a href="{{ route('public.posts') }}" class="rounded-control text-ink-soft hover:text-brand hover:underline">Berita</a></li>
                    <li class="flex items-center gap-1.5"><x-heroicon-o-chevron-right class="size-3.5" aria-hidden="true" /><a href="{{ route('public.posts', ['kategori' => $post->category->value]) }}" class="rounded-control text-ink-soft hover:text-brand hover:underline">{{ $post->category->label() }}</a></li>
                </ol>
            </nav>

            @include('publik.posts._meta', ['post' => $post, 'class' => 'mt-8'])
            <h1 class="mt-3 font-serif text-display-sm font-semibold text-balance text-ink md:text-title-lg">{{ $post->title }}</h1>
            @if ($post->summary)
                <p class="mt-5 text-title-sm text-ink-soft">{{ $post->summary }}</p>
            @endif
            <p class="mt-6 flex flex-wrap items-center gap-x-2 border-t border-line pt-4 text-caption text-ink-muted">
                <span>Diterbitkan oleh <span class="font-semibold text-ink-soft">Kantor Distrik Ransiki</span></span>
                @if ($updated)
                    <span aria-hidden="true">·</span>
                    <span>Diperbarui <time datetime="{{ $updated->toAtomString() }}">{{ Format::date($updated) }}</time></span>
                @endif
            </p>
        </header>

        @if ($cover)
            {{-- Foto lebih lebar dari kolom baca (sekitar 960px). --}}
            <figure class="mx-auto mt-10 max-w-[63rem] px-4 md:px-6">
                <x-public.picture :image="$cover" :alt="$post->photo_alt ?: $post->title" eager sizes="(min-width: 64rem) 60rem, 100vw" width="1600" height="900" frame="block aspect-[16/9] overflow-hidden rounded-tile bg-subtle" class="size-full object-cover" />
                @if ($post->photo_caption)
                    <figcaption class="mt-3 text-caption text-ink-muted">{{ $post->photo_caption }}</figcaption>
                @endif
            </figure>
        @endif

        <div class="prose-article mx-auto mt-10 max-w-[40rem] px-4">{!! Format::markdown($post->body) !!}</div>

        <div class="mx-auto mt-12 max-w-[40rem] space-y-10 px-4">
            {{-- Bagikan: tautan biasa, tanpa skrip pihak ketiga. --}}
            <section aria-labelledby="bagikan" class="border-t border-line pt-8 print:hidden">
                <h2 id="bagikan" class="text-caption font-semibold tracking-wider text-ink-soft uppercase">Bagikan</h2>
                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach ($share as [$label, $href, $icon])
                        <a href="{{ $href }}" target="_blank" rel="noopener" class="{{ $chip }}">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-4" aria-hidden="true" />{{ $label }}
                        </a>
                    @endforeach
                    <button type="button" x-data="copyText(@js($url))" x-on:click="copy()" class="{{ $chip }}">
                        <x-heroicon-o-link class="size-4" aria-hidden="true" />
                        <span x-text="copied ? 'Tautan disalin' : 'Salin tautan'">Salin tautan</span>
                        <span class="sr-only" aria-live="polite" x-text="copied ? 'Tautan disalin' : ''"></span>
                    </button>
                </div>
            </section>

            @if ($post->organizations->isNotEmpty())
                <section aria-labelledby="kelompok-terkait" class="print:hidden">
                    <h2 id="kelompok-terkait" class="font-serif text-page-title font-semibold text-ink">Kelompok terkait</h2>
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($post->organizations as $organization)
                            <li>
                                <a href="{{ route('public.organization', $organization->slug) }}" class="group flex h-full items-center justify-between gap-3 rounded-public border border-line bg-paper p-4 transition-colors hover:border-brand">
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-ink group-hover:text-brand">{{ $organization->name }}</span>
                                        <span class="mt-0.5 block text-caption text-ink-muted">{{ $organization->institutionType->name }} · {{ $organization->village->name }}</span>
                                    </span>
                                    <x-heroicon-o-arrow-right class="size-4 shrink-0 text-brand" aria-hidden="true" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($previous || $next)
                <nav aria-label="Berita sebelumnya dan berikutnya" class="grid gap-3 border-t border-line pt-8 sm:grid-cols-2 print:hidden">
                    @if ($previous)
                        <a href="{{ route('public.post', $previous->slug) }}" class="group rounded-public border border-line bg-paper p-4 transition-colors hover:border-brand">
                            <span class="flex items-center gap-1 text-caption text-ink-muted"><x-heroicon-o-chevron-left class="size-4" aria-hidden="true" />Sebelumnya</span>
                            <span class="mt-1 line-clamp-2 block font-serif font-semibold text-ink group-hover:text-brand">{{ $previous->title }}</span>
                        </a>
                    @endif
                    @if ($next)
                        <a href="{{ route('public.post', $next->slug) }}" @class(['group rounded-public border border-line bg-paper p-4 text-right transition-colors hover:border-brand', 'sm:col-start-2' => ! $previous])>
                            <span class="flex items-center justify-end gap-1 text-caption text-ink-muted">Berikutnya<x-heroicon-o-chevron-right class="size-4" aria-hidden="true" /></span>
                            <span class="mt-1 line-clamp-2 block font-serif font-semibold text-ink group-hover:text-brand">{{ $next->title }}</span>
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </article>

    @if ($others->isNotEmpty())
        {{-- Section sendiri berlatar putih: padding sama, tidak menumpuk dengan artikel di atasnya. --}}
        <section class="border-t border-line bg-paper print:hidden" aria-labelledby="berita-lainnya">
            <div class="mx-auto max-w-public px-4 py-section md:px-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 id="berita-lainnya" class="font-serif text-display-sm font-semibold text-ink">Berita lainnya</h2>
                    <x-public.pill-link :href="route('public.posts')" class="max-md:hidden">Lihat semua berita</x-public.pill-link>
                </div>
                <ul class="mt-section-head grid gap-x-8 gap-y-12 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($others as $other)
                        <li class="min-w-0">@include('publik._post-card', ['post' => $other])</li>
                    @endforeach
                </ul>
                <x-public.pill-link :href="route('public.posts')" full class="mt-8 md:hidden">Lihat semua berita</x-public.pill-link>
            </div>
        </section>
    @endif
</x-layouts.public>
