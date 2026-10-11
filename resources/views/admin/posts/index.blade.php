@use('App\Support\Format')
@use('App\Enums\PostCategory')

@php
    $url = route('admin.posts.index');
    $statuses = ['draf' => 'Draf', 'terjadwal' => 'Terjadwal', 'terbit' => 'Sudah tampil'];
    $active = collect($filters)->only(['kategori', 'status'])->filter(fn ($value) => filled($value))->count();
    $chips = \App\Support\FilterChips::from($filters, [
        'q' => [null, null],
        'kategori' => ['Kategori', PostCategory::options()],
        'status' => ['Status', $statuses],
    ]);
@endphp

<x-layouts.app title="Berita & Kegiatan" description="Berita dan kegiatan yang tampil di menu Berita website publik.">
    <x-slot:actions>
        <x-button icon="plus" :href="route('admin.posts.create')">Tambah berita</x-button>
    </x-slot:actions>

    <div class="space-y-4">
        <x-filter-bar :action="$url" search-label="Cari judul" :search="$filters['q'] ?? ''" :active="$active">
            <x-select size="sm" name="kategori" :options="PostCategory::options()" :selected="$filters['kategori'] ?? null" placeholder="Semua kategori" aria-label="Kategori" x-on:change="$el.form.requestSubmit()" />
            <x-select size="sm" name="status" :options="$statuses" :selected="$filters['status'] ?? null" placeholder="Semua status" aria-label="Status" x-on:change="$el.form.requestSubmit()" />
        </x-filter-bar>

        <x-filter-summary :summary="$posts->total().' berita'" :chips="$chips" :clear-url="$url" />

        @if ($posts->isEmpty())
            @if ($chips !== [])
                <x-empty-state title="Tidak ada berita yang cocok" description="Ubah kata pencarian atau hapus filter.">
                    <x-button variant="secondary" :href="$url">Hapus filter</x-button>
                </x-empty-state>
            @else
                <x-empty-state title="Belum ada berita" description="Tulis berita atau kegiatan pertama untuk website publik.">
                    <x-button icon="plus" :href="route('admin.posts.create')">Tambah berita</x-button>
                </x-empty-state>
            @endif
        @else
            <x-table clickable>
                <x-slot:head>
                    <x-table.heading>Judul</x-table.heading>
                    <x-table.heading>Kategori</x-table.heading>
                    <x-table.heading>Tanggal terbit</x-table.heading>
                    <x-table.heading>Status</x-table.heading>
                </x-slot:head>

                @foreach ($posts as $post)
                    <x-table.row :href="route('admin.posts.edit', $post)">
                        <x-table.cell primary>{{ $post->title }}</x-table.cell>
                        <x-table.cell label="Kategori">{{ $post->category->label() }}</x-table.cell>
                        <x-table.cell label="Tanggal terbit">{{ $post->published_at ? Format::dateTime($post->published_at) : Format::EMPTY }}</x-table.cell>
                        <x-table.cell badge>
                            @if ($post->isScheduled())
                                <x-badge tone="wait">Terjadwal</x-badge>
                            @else
                                <x-badge :tone="$post->status->badgeColor()">{{ $post->status->label() }}</x-badge>
                            @endif
                        </x-table.cell>
                    </x-table.row>
                @endforeach

                @if ($posts->hasPages())
                    <x-slot:footer>{{ $posts->links() }}</x-slot:footer>
                @endif
            </x-table>
        @endif
    </div>
</x-layouts.app>
