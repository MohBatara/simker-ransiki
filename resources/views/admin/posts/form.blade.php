{{-- Tambah/ubah Berita & Kegiatan. Foto sampul diunggah setelah berita tersimpan (XHR, seperti foto lain). --}}
@use('App\Enums\PostCategory')
@use('App\Enums\PostStatus')

@php
    $editing = $post->exists;
    $selected = array_map('intval', old('organizations', $post->organizations?->pluck('id')->all() ?? []));
    $publishedAt = old('published_at', $post->published_at?->format('Y-m-d\TH:i'));
@endphp

<x-layouts.app
    :title="$editing ? 'Ubah berita' : 'Tambah berita'"
    :description="$editing ? $post->title : 'Berita atau kegiatan untuk website publik.'"
    :back="route('admin.posts.index')"
    focus
>
    <div class="space-y-5">
        <form id="form-post" method="POST" action="{{ $editing ? route('admin.posts.update', $post) : route('admin.posts.store') }}" class="space-y-5">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif
            <x-form-errors />

            <x-card title="Isi">
                <div class="space-y-5">
                    <x-field label="Judul" for="title">
                        <x-input name="title" :value="$post->title" maxlength="90" required />
                    </x-field>
                    @php $category = old('category', $post->category?->value ?? PostCategory::News->value); @endphp
                    <div class="grid gap-5 md:grid-cols-2" x-data="{ category: @js($category) }" x-on:change="if ($event.target.name === 'category') category = $event.target.value">
                        <x-field label="Kategori" for="category">
                            <x-select name="category" :options="PostCategory::options()" :selected="$category" required />
                        </x-field>
                        <x-field label="Alamat halaman" for="slug" hint="Bagian akhir tautan, mis. panen-kakao-2026. Kosongkan agar dibuat dari judul." optional>
                            <x-input name="slug" :value="$post->slug" maxlength="200" autocapitalize="none" spellcheck="false" />
                        </x-field>
                        {{-- Khusus Kegiatan: tanggal pelaksanaan untuk Agenda di halaman Berita (kategori lain dikosongkan saat simpan). --}}
                        <div x-show="category === @js(PostCategory::Activity->value)" @if ($category !== PostCategory::Activity->value) style="display: none" @endif>
                            <x-field label="Tanggal kegiatan" for="event_date" hint="Tanggal pelaksanaan. Kegiatan yang akan datang tampil lebih dulu di Agenda. Kosongkan untuk memakai tanggal terbit." optional>
                                <x-input name="event_date" type="date" :value="old('event_date', $post->event_date?->format('Y-m-d'))" />
                            </x-field>
                        </div>
                    </div>
                    <x-field label="Ringkasan" for="summary" hint="Satu atau dua kalimat untuk daftar berita, paragraf pembuka, dan pratinjau tautan di WhatsApp. Maksimal 200 karakter." optional>
                        <x-textarea name="summary" :value="$post->summary" rows="2" maxlength="200" />
                    </x-field>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-field label="Teks alternatif foto sampul" for="photo_alt" :hint="'Jelaskan isi foto untuk pembaca layar, mis. Petani menjemur biji kakao. '.($post->cover_photo_path ? 'Wajib karena berita ini berfoto.' : 'Wajib setelah foto sampul diunggah.')" :optional="! $post->cover_photo_path">
                            <x-input name="photo_alt" :value="$post->photo_alt" maxlength="150" />
                        </x-field>
                        <x-field label="Keterangan foto sampul" for="photo_caption" hint="Tampil kecil di bawah foto, mis. nama tempat atau fotografer." optional>
                            <x-input name="photo_caption" :value="$post->photo_caption" maxlength="150" />
                        </x-field>
                    </div>

                    <div x-data="markdownPreview(@js(route('admin.posts.preview')))" class="space-y-2">
                        <label for="body" class="block text-ui font-semibold text-ink">Isi berita</label>
                        <x-tabs :tabs="['tulis' => 'Tulis', 'pratinjau' => 'Pratinjau']">
                            <x-tabs.panel name="tulis">
                                <x-textarea name="body" :value="$post->body" rows="14" maxlength="50000" x-ref="source" required />
                                <p class="mt-2 text-caption text-ink-muted">Format Markdown: ## Subjudul, **tebal**, *miring*, - daftar, [teks tautan](https://alamat). HTML tidak didukung.</p>
                            </x-tabs.panel>
                            <x-tabs.panel name="pratinjau" x-init="$watch('tab', value => value === 'pratinjau' && load())">
                                <div class="min-h-40 rounded-control border border-line bg-paper p-4" aria-live="polite">
                                    <p x-show="loading" class="text-ink-muted">Memuat pratinjau…</p>
                                    <p x-cloak x-show="failed && ! loading" class="text-fix">Pratinjau gagal dimuat. Periksa koneksi lalu coba lagi.</p>
                                    <p x-cloak x-show="! loading && ! failed && html === ''" class="text-ink-muted">Isi berita masih kosong.</p>
                                    <div x-cloak x-show="! loading && ! failed" class="prose-article" x-html="html"></div>
                                </div>
                            </x-tabs.panel>
                        </x-tabs>
                    </div>
                </div>
            </x-card>

            <x-card title="Kelompok terkait" description="Opsional. Hanya kelompok yang tampil di direktori publik yang ditautkan di halaman berita.">
                <div x-data="{ q: '' }" class="space-y-3">
                    <x-field label="Cari kelompok" for="organization-search">
                        <x-input type="search" id="organization-search" x-model="q" placeholder="Ketik nama kelompok atau kampung" autocomplete="off" x-on:keydown.enter.prevent />
                    </x-field>
                    <div class="max-h-72 overflow-y-auto rounded-control border border-line px-3">
                        @foreach ($organizations as $organization)
                            <div x-show="@js(mb_strtolower($organization['label'])).includes(q.toLowerCase())">
                                <x-checkbox name="organizations[]" :value="$organization['id']" :checked="in_array($organization['id'], $selected, true)" :hint="$organization['note']">{{ $organization['label'] }}</x-checkbox>
                            </div>
                        @endforeach
                    </div>
                    @error('organizations')
                        <p class="text-caption text-fix">{{ $message }}</p>
                    @enderror
                </div>
            </x-card>

            <x-card title="Penerbitan">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-field label="Status" for="status" hint="Draf tidak pernah tampil di website.">
                        <x-select name="status" :options="PostStatus::options()" :selected="old('status', $post->status?->value ?? PostStatus::Draft->value)" required />
                    </x-field>
                    <x-field label="Tanggal terbit" for="published_at" hint="Kosongkan untuk terbit sekarang. Isi tanggal ke depan untuk menjadwalkan (WIT)." optional>
                        <x-input name="published_at" type="datetime-local" :value="$publishedAt" />
                    </x-field>
                </div>
            </x-card>
        </form>

        @if ($editing)
            <x-card title="Foto sampul" description="Opsional. Tampil di atas berita dan sebagai gambar pratinjau tautan.">
                <div class="space-y-4">
                    @if ($sources = $post->coverSources())
                        <img src="{{ $sources['webp'][960] ?? $sources['fallback'] }}" alt="Foto sampul saat ini" class="aspect-[4/3] w-full rounded-card object-cover md:aspect-[21/9]">
                        <form method="POST" action="{{ route('admin.posts.cover.destroy', $post) }}">
                            @csrf
                            @method('DELETE')
                            <x-button variant="ghost" type="submit" processing="Menghapus…">Hapus foto</x-button>
                        </form>
                    @endif
                    <x-file-upload :url="route('admin.posts.cover.store', $post)" kind="photo" :multiple="false" :remaining="1" hint="Foto JPG atau PNG, maksimal 10 MB. Foto diperkecil dan data lokasinya dibuang otomatis. Foto lama akan diganti." />
                </div>
            </x-card>

            <x-card title="Hapus berita">
                <p class="text-ink-soft">Berita dan foto sampulnya dihapus permanen.</p>
                <x-button variant="danger" class="mt-4" x-data x-on:click="$dispatch('open-modal', 'delete-post')">Hapus berita</x-button>
            </x-card>
        @else
            <p class="text-caption text-ink-muted">Foto sampul bisa ditambahkan setelah berita disimpan.</p>
        @endif

        <x-form-actions sticky>
            <x-button variant="ghost" :href="route('admin.posts.index')">Batal</x-button>
            <x-button type="submit" form="form-post" processing="Menyimpan…">Simpan</x-button>
        </x-form-actions>
    </div>

    @if ($editing)
        <x-modal name="delete-post" title="Hapus berita">
            <p><strong class="text-ink">{{ $post->title }}</strong> akan dihapus dan tidak tampil lagi di website.</p>
            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'delete-post')">Batal</x-button>
                <form method="POST" action="{{ route('admin.posts.destroy', $post) }}">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" type="submit" processing="Menghapus…">Hapus</x-button>
                </form>
            </x-slot:footer>
        </x-modal>
    @endif
</x-layouts.app>
