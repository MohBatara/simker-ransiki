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
    $round = 'flex size-10 items-center justify-center rounded-full border border-line-strong bg-paper text-ink transition-colors hover:border-brand hover:text-brand';
@endphp

<x-layouts.public :title="$post->title" :description="$description" :image="$image" type="article">
    <x-slot:meta>
        <meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">
        <meta property="article:modified_time" content="{{ ($post->updated_at ?? $post->published_at)->toAtomString() }}">
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </x-slot:meta>

    {{-- Garis progres membaca: menempel di bawah header (ikut menyusut), lebar mengikuti posisi gulir artikel. --}}
    <div
        x-data="readingProgress"
        data-reading-progress
        class="reading-progress pointer-events-none fixed inset-x-0 z-40 h-[3px] print:hidden"
        x-bind:class="$store.scroll.pastHeader ? 'top-13 md:top-15' : 'top-16 md:top-18'"
        aria-hidden="true"
    >
        <div x-ref="bar" class="h-full origin-left scale-x-0 bg-sun"></div>
    </div>

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

            <div data-intro>
                <a href="{{ route('public.posts', ['kategori' => $post->category->value]) }}" class="mt-8 inline-flex h-7 items-center rounded-full bg-brand-tint px-3 text-caption font-semibold text-brand transition-colors hover:bg-brand hover:text-paper">{{ $post->category->label() }}</a>
                <h1 class="mt-4 font-serif text-[1.875rem] leading-[2.375rem] font-semibold tracking-[-0.01em] text-balance text-ink md:text-[2.75rem] md:leading-[3.25rem]">{{ $post->title }}</h1>
                @if ($post->summary)
                    <p class="mt-5 text-title-sm text-ink-soft">{{ $post->summary }}</p>
                @endif
                {{-- Baris penerbit: inisial, nama, tanggal terbit, waktu baca, diperbarui (bila beda hari). --}}
                <div class="mt-6 flex items-center gap-3 border-t border-line pt-5">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-forest font-serif text-ui font-semibold text-paper" aria-hidden="true">KD</span>
                    <div class="min-w-0 text-caption text-ink-soft">
                        <p class="font-semibold text-ink">Kantor Distrik Ransiki</p>
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-2">
                            <time datetime="{{ $post->published_at->toAtomString() }}">{{ Format::date($post->published_at) }}</time>
                            <span aria-hidden="true">·</span>
                            <span>{{ $post->readingMinutes() }} menit baca</span>
                            @if ($updated)
                                <span aria-hidden="true">·</span>
                                <span>Diperbarui <time datetime="{{ $updated->toAtomString() }}">{{ Format::date($updated) }}</time></span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </header>

        @if ($cover)
            {{-- Foto lebih lebar dari kolom baca (sekitar 1040px), sudut 20px. --}}
            <figure class="mx-auto mt-10 max-w-[65rem] px-4 md:px-6" x-reveal="fade-up">
                <x-public.picture :image="$cover" :alt="$post->photo_alt ?: $post->title" eager sizes="(min-width: 64rem) 65rem, 100vw" width="1600" height="900" frame="block aspect-[16/9] overflow-hidden rounded-[20px] bg-subtle" class="size-full object-cover" />
                @if ($post->photo_caption)
                    <figcaption class="mt-3 text-caption text-ink-muted">{{ $post->photo_caption }}</figcaption>
                @endif
            </figure>
        @endif

        {{-- Isi + rel bagikan (desktop: menempel di kiri kolom baca; HP: baris tombol di bawah artikel). --}}
        <div class="relative mx-auto mt-10 max-w-[40rem] px-4">
            <div class="absolute top-0 right-full bottom-0 mr-4 hidden lg:block print:hidden">
                <div class="sticky top-36 flex flex-col items-center gap-3">
                    <span class="text-caption font-semibold tracking-wider text-ink-muted uppercase [writing-mode:vertical-rl]" aria-hidden="true">Bagikan</span>
                    @foreach ($share as [$label, $href, $icon])
                        <a href="{{ $href }}" target="_blank" rel="noopener" class="{{ $round }}" title="Bagikan ke {{ $label }}">
                            <span class="sr-only">Bagikan ke {{ $label }}</span><x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5" aria-hidden="true" />
                        </a>
                    @endforeach
                    <button type="button" x-data="copyText(@js($url))" x-on:click="copy()" class="{{ $round }}" x-bind:title="copied ? 'Tautan disalin' : 'Salin tautan'" title="Salin tautan">
                        <span class="sr-only" x-text="copied ? 'Tautan disalin' : 'Salin tautan'">Salin tautan</span>
                        <x-heroicon-o-link class="size-5" aria-hidden="true" x-show="! copied" />
                        <x-heroicon-o-check class="size-5 text-brand" aria-hidden="true" x-show="copied" x-cloak />
                    </button>
                </div>
            </div>
            <div class="prose-article drop-cap">{!! Format::markdown($post->body) !!}</div>
        </div>

        <div class="mx-auto mt-12 max-w-[40rem] space-y-10 px-4">
            {{-- Bagikan: tautan biasa, tanpa skrip pihak ketiga. --}}
            <section aria-labelledby="bagikan" class="border-t border-line pt-8 lg:hidden print:hidden">
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
                    @foreach (['previous' => [$previous, 'Sebelumnya'], 'next' => [$next, 'Berikutnya']] as $key => [$item, $label])
                        @if ($item)
                            @php $thumb = $item->coverSources(); @endphp
                            <a href="{{ route('public.post', $item->slug) }}" @class([
                                'group flex items-center gap-4 rounded-tile border border-line bg-paper p-3 transition-colors hover:border-brand',
                                'flex-row-reverse text-right' => $key === 'next',
                                'sm:col-start-2' => $key === 'next' && ! $previous,
                            ])>
                                <span class="block size-18 shrink-0 overflow-hidden rounded-photo bg-cream">
                                    @if ($thumb)
                                        <x-public.picture :image="$thumb" alt="" sizes="72px" width="480" height="300" frame="block size-full" class="size-full object-cover" />
                                    @else
                                        <span class="flex size-full items-center justify-center text-clay" aria-hidden="true"><x-dynamic-component :component="'heroicon-o-'.$item->category->icon()" class="size-7" /></span>
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span @class(['flex items-center gap-1 text-caption text-ink-muted', 'justify-end' => $key === 'next'])>
                                        @if ($key === 'previous')<x-heroicon-o-chevron-left class="size-4" aria-hidden="true" />@endif{{ $label }}@if ($key === 'next')<x-heroicon-o-chevron-right class="size-4" aria-hidden="true" />@endif
                                    </span>
                                    <span class="mt-1 line-clamp-2 block font-serif font-semibold text-ink group-hover:text-brand">{{ $item->title }}</span>
                                </span>
                            </a>
                        @endif
                    @endforeach
                </nav>
            @endif
        </div>
    </article>

    @if ($others->isNotEmpty())
        {{-- Section sendiri berlatar putih: padding sama, tidak menumpuk dengan artikel di atasnya. --}}
        <section class="border-t border-line bg-paper print:hidden" aria-labelledby="berita-lainnya">
            <div class="mx-auto max-w-public px-4 py-section md:px-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 id="berita-lainnya" class="font-serif text-display-sm font-semibold text-ink">Baca juga</h2>
                    <x-public.pill-link :href="route('public.posts')" class="max-md:hidden">Lihat semua berita</x-public.pill-link>
                </div>
                <ul class="mt-section-head grid gap-x-8 gap-y-12 md:grid-cols-2 lg:grid-cols-3" x-reveal.stagger="fade-up">
                    @foreach ($others as $other)
                        <li class="min-w-0">@include('publik._post-card', ['post' => $other])</li>
                    @endforeach
                </ul>
                <x-public.pill-link :href="route('public.posts')" full class="mt-8 md:hidden">Lihat semua berita</x-public.pill-link>
            </div>
        </section>
    @endif
</x-layouts.public>
