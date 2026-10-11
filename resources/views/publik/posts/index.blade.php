<x-layouts.public title="Berita & kegiatan" description="Berita dan kegiatan kelompok usaha dan kelembagaan ekonomi Distrik Ransiki.">
    <x-public.band title="Berita & kegiatan" :crumbs="['Berita' => null]" lead="Kabar terbaru dari kelompok usaha dan Pemerintah Distrik Ransiki." />

    <div class="mx-auto max-w-public px-4 py-8 md:px-6 md:py-10">
        <nav aria-label="Kategori" class="flex flex-wrap gap-2">
            @foreach (['' => 'Semua'] + App\Enums\PostCategory::options() as $value => $label)
                @php $current = ($category?->value ?? '') === $value; @endphp
                <a
                    href="{{ route('public.posts', array_filter(['kategori' => $value])) }}"
                    @if ($current) aria-current="page" @endif
                    @class([
                        'inline-flex h-11 items-center rounded-full px-4 text-ui transition-colors',
                        'bg-brand font-semibold text-paper' => $current,
                        'border border-line-strong bg-paper text-ink-soft hover:text-ink' => ! $current,
                    ])
                >{{ $label }}</a>
            @endforeach
        </nav>

        @if ($posts->isEmpty())
            <div class="mt-8">
                <x-empty-state
                    :title="$category ? 'Belum ada '.mb_strtolower($category->label()).' yang diterbitkan' : 'Belum ada berita yang diterbitkan'"
                    description="Berita dan kegiatan tampil di sini setelah diterbitkan Admin distrik."
                />
            </div>
        @else
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    @include('publik._post-card')
                @endforeach
            </div>
            <div class="mt-8">{{ $posts->links() }}</div>
        @endif
    </div>
</x-layouts.public>
