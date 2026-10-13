@use('App\Enums\PostCategory')
@use('App\Support\Format')

@php
    $tabs = ['' => 'Semua'] + PostCategory::options();
    $listUrl = fn (?string $kategori) => route('public.posts', array_filter(['kategori' => $kategori, 'q' => $search]));
@endphp

<x-layouts.public title="Berita dan kegiatan" description="Berita dan kegiatan kelompok usaha dan kelembagaan ekonomi Distrik Ransiki.">
    <x-public.band title="Berita dan kegiatan" :crumbs="['Berita' => null]" lead="Kabar terbaru dari kelompok usaha dan Pemerintah Distrik Ransiki." />

    {{-- Bar alat: tab kategori (dengan jumlah) dan cari judul. Keduanya lewat query string agar bisa dibagikan. --}}
    <div class="border-b border-line bg-paper">
        <div class="mx-auto flex max-w-public flex-col gap-4 px-4 pt-2 max-md:pb-4 md:flex-row md:items-center md:justify-between md:px-6 md:pt-0">
            <nav aria-label="Kategori berita" class="-mb-px overflow-x-auto max-md:border-b max-md:border-line">
                <ul class="flex gap-6">
                    @foreach ($tabs as $value => $label)
                        @php $current = ($category?->value ?? '') === $value; @endphp
                        <li>
                            <a
                                href="{{ $listUrl($value ?: null) }}"
                                @if ($current) aria-current="page" @endif
                                @class([
                                    'inline-flex h-14 items-center gap-2 border-b-2 whitespace-nowrap text-ui transition-colors',
                                    'border-brand font-semibold text-ink' => $current,
                                    'border-transparent text-ink-soft hover:text-ink' => ! $current,
                                ])
                            >
                                {{ $label }}
                                <span class="rounded-full bg-subtle px-2 text-caption text-ink-soft tabular-nums">{{ Format::number($counts[$value]) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <form method="GET" action="{{ route('public.posts') }}" role="search" class="flex gap-2 md:w-96">
                @if ($category)
                    <input type="hidden" name="kategori" value="{{ $category->value }}">
                @endif
                <label for="q" class="sr-only">Cari judul berita</label>
                <x-input type="search" name="q" :value="$search" placeholder="Cari judul berita" class="min-w-0 flex-1" />
                <x-public.button type="submit" variant="brand" size="md" icon="magnifying-glass">Cari</x-public.button>
            </form>
        </div>
    </div>

    <div class="mx-auto max-w-public px-4 py-section md:px-6">
        @if ($search !== '')
            <p class="mb-8 text-ui text-ink-soft" aria-live="polite">
                {{ Format::number($posts->total()) }} berita cocok dengan "<span class="font-semibold text-ink">{{ $search }}</span>".
                <a href="{{ route('public.posts', array_filter(['kategori' => $category?->value])) }}" class="font-semibold text-brand underline underline-offset-4 hover:text-brand-hover">Hapus pencarian</a>
            </p>
        @endif

        @if ($lead)
            {{-- Berita utama horizontal: foto 7 kolom, teks 5 kolom. --}}
            @php $leadCover = $lead->coverSources(); @endphp
            <article class="group relative grid gap-6 has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-4 has-[a:focus-visible]:outline-brand lg:grid-cols-12 lg:items-center lg:gap-x-12">
                <div class="aspect-[16/10] overflow-hidden rounded-tile bg-cream lg:col-span-7">
                    @if ($leadCover)
                        <x-public.picture :image="$leadCover" alt="" sizes="(min-width: 64rem) 37rem, 100vw" width="960" height="600" frame="block size-full" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100" />
                    @else
                        <span class="flex size-full items-center justify-center text-clay" aria-hidden="true">
                            <x-dynamic-component :component="'heroicon-o-'.$lead->category->icon()" class="size-20" />
                        </span>
                    @endif
                </div>
                <div class="min-w-0 lg:col-span-5">
                    @include('publik.posts._meta', ['post' => $lead])
                    <h2 class="mt-3 font-serif text-headline font-semibold text-ink lg:text-display"><a href="{{ route('public.post', $lead->slug) }}" class="after:absolute after:inset-0 group-hover:text-brand focus-visible:outline-none">{{ $lead->title }}</a></h2>
                    @if ($leadSummary = $lead->summary ?: Format::excerpt($lead->body, 200))
                        <p class="mt-3 line-clamp-3 text-body leading-[1.6] text-ink-soft">{{ $leadSummary }}</p>
                    @endif
                    <span class="mt-4 inline-flex items-center gap-2 font-semibold text-brand underline-offset-4 group-hover:underline" aria-hidden="true">
                        Baca selengkapnya
                        <x-heroicon-o-arrow-right class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none" />
                    </span>
                </div>
            </article>
        @endif

        @if ($posts->isNotEmpty())
            <ul @class(['grid gap-x-8 gap-y-12 md:grid-cols-2 lg:grid-cols-3', 'mt-section' => $lead])>
                @foreach ($posts as $post)
                    <li class="min-w-0">@include('publik._post-card', ['post' => $post, 'level' => $lead ? 'h3' : 'h2'])</li>
                @endforeach
            </ul>
            @if ($posts->hasPages())
                <div class="mt-section-head">{{ $posts->onEachSide(1)->links('publik._pagination') }}</div>
            @endif
        @elseif (! $lead)
            <div class="mx-auto max-w-[32rem] rounded-public border border-line bg-paper px-6 py-12 text-center">
                <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-cream text-clay" aria-hidden="true">
                    <x-heroicon-o-newspaper class="size-7" />
                </span>
                <h2 class="mt-5 font-serif text-page-title font-semibold text-ink">
                    {{ $search !== '' ? 'Tidak ada berita yang cocok' : ($category ? 'Belum ada '.mb_strtolower($category->label()).' yang diterbitkan' : 'Belum ada berita yang diterbitkan') }}
                </h2>
                <p class="mt-3 text-ui text-ink-soft">
                    {{ $search !== '' ? 'Coba kata lain, atau lihat semua berita dan kegiatan.' : 'Berita dan kegiatan tampil di sini setelah diterbitkan Admin distrik.' }}
                </p>
                @if ($search !== '' || $category)
                    <x-public.pill-link :href="route('public.posts')" class="mt-6">Lihat semua berita</x-public.pill-link>
                @endif
            </div>
        @endif
    </div>
</x-layouts.public>
