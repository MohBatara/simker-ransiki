@use('App\Support\Format')
@use('App\Enums\PostCategory')

@php
    $url = route('admin.posts.index');
    $statuses = ['draf' => 'Draf', 'terjadwal' => 'Terjadwal', 'terbit' => 'Sudah tampil'];
    $chips = \App\Support\FilterChips::from($filters, [
        'q' => [null, null],
        'kategori' => ['Kategori', PostCategory::options()],
        'status' => ['Status', $statuses],
    ]);
    $toolbarFilters = [
        ['name' => 'kategori', 'label' => 'Kategori', 'options' => PostCategory::options(), 'selected' => $filters['kategori'] ?? null, 'placeholder' => 'Semua kategori'],
        ['name' => 'status', 'label' => 'Status', 'options' => $statuses, 'selected' => $filters['status'] ?? null, 'placeholder' => 'Semua status'],
    ];
@endphp

<x-layouts.app title="Berita & kegiatan" description="Berita dan kegiatan yang tampil di menu Berita website publik." section="website" tab="berita">
    <x-slot:actions>
        <x-button icon="plus" :href="route('admin.posts.create')">Tambah berita</x-button>
    </x-slot:actions>

    <x-admin.card flush>
        <x-admin.toolbar
            title="Daftar berita"
            :action="$url"
            search-label="Cari judul"
            :search="$filters['q'] ?? ''"
            :filters="$toolbarFilters"
            :chips="$chips"
            :summary="Format::number($posts->total()).' berita'"
            :clear-url="$chips !== [] ? $url : null"
        />

        {{-- Ditukar oleh x-admin.toolbar saat filter berubah. --}}
        <div data-swap="berita">
            @if ($posts->isEmpty())
                @if ($chips !== [])
                    <x-admin.empty bare icon="magnifying-glass" title="Tidak ada berita yang cocok" description="Ubah kata pencarian atau hapus filter.">
                        <x-button variant="secondary" :href="$url">Hapus filter</x-button>
                    </x-admin.empty>
                @else
                    <x-admin.empty bare icon="newspaper" title="Belum ada berita" description="Tulis berita atau kegiatan pertama untuk website publik.">
                        <x-button icon="plus" :href="route('admin.posts.create')">Tambah berita</x-button>
                    </x-admin.empty>
                @endif
            @else
                <x-admin.table bare clickable>
                    <x-slot:head>
                        <x-admin.table.heading>Judul</x-admin.table.heading>
                        <x-admin.table.heading>Kategori</x-admin.table.heading>
                        <x-admin.table.heading>Tanggal terbit</x-admin.table.heading>
                        <x-admin.table.heading>Status</x-admin.table.heading>
                    </x-slot:head>

                    @foreach ($posts as $post)
                        <x-admin.table.row :href="route('admin.posts.edit', $post)" :for="$post->title">
                            <x-admin.table.cell primary>{{ $post->title }}</x-admin.table.cell>
                            <x-admin.table.cell label="Kategori"><x-admin.chip :icon="$post->category === PostCategory::Activity ? 'calendar-days' : 'newspaper'">{{ $post->category->label() }}</x-admin.chip></x-admin.table.cell>
                            <x-admin.table.cell label="Tanggal terbit"><x-admin.date :value="$post->published_at" time /></x-admin.table.cell>
                            <x-admin.table.cell badge>
                                @if ($post->isScheduled())
                                    <x-admin.badge-status tone="warning" label="Terjadwal" />
                                @else
                                    <x-admin.badge-status :status="$post->status" />
                                @endif
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach

                    @if ($posts->hasPages())
                        <x-slot:footer><x-admin.pagination :paginator="$posts" noun="berita" /></x-slot:footer>
                    @endif
                </x-admin.table>
            @endif
        </div>
    </x-admin.card>
</x-layouts.app>
