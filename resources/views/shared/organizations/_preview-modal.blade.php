{{--
    Popup pratinjau kelompok (lg) untuk daftar Kelompok, Verifikasi profil, dan Kemungkinan data ganda. Harus berada di dalam
    elemen x-data="organizationPanel(...)" yang juga membungkus tabelnya. Panah kiri/kanan (tombol dan keyboard) pindah ke
    kelompok sebelumnya/berikutnya di daftar yang tampil. Variabel: $panel (opsional, dirender server untuk ?pratinjau=ID), $area.
--}}
<x-admin.modal name="organization-preview" title="Pratinjau kelompok" size="lg" :show="(bool) ($panel ?? null)" :guard="false"
    x-on:modal-closed="closed()" x-on:keydown.arrow-left="go(-1)" x-on:keydown.arrow-right="go(1)">
    <div data-preview-panel x-bind:class="loading && 'opacity-60'" x-bind:aria-busy="loading" aria-live="polite" class="transition-opacity">
        @if ($panel ?? null)
            @include('shared.organizations._panel')
        @endif
    </div>
    <x-slot:footer>
        <div class="mr-auto flex gap-1">
            <x-button variant="secondary" class="px-2.5" x-on:click="go(-1)" x-bind:disabled="! hasPrevious" aria-label="Kelompok sebelumnya" title="Kelompok sebelumnya (←)">
                <x-heroicon-o-chevron-left class="size-5" />
            </x-button>
            <x-button variant="secondary" class="px-2.5" x-on:click="go(1)" x-bind:disabled="! hasNext" aria-label="Kelompok berikutnya" title="Kelompok berikutnya (→)">
                <x-heroicon-o-chevron-right class="size-5" />
            </x-button>
        </div>
        <x-button variant="secondary" x-on:click="requestClose()">Tutup</x-button>
        <x-button variant="secondary" icon="pencil-square" x-show="editUrl" x-bind:href="editUrl" href="#">Ubah profil</x-button>
        <x-button x-bind:href="detailUrl" href="#">Buka detail</x-button>
    </x-slot:footer>
</x-admin.modal>
