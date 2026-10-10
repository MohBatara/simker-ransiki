<x-layouts.guest title="Terjadi kesalahan" heading="Terjadi kesalahan di server">
    <p class="text-ui text-ink-soft">Permintaan Anda belum bisa diproses. Coba lagi beberapa saat lagi. Bila terus terjadi, hubungi Admin distrik.</p>
    @include('errors._actions', ['reload' => true])
    <x-slot:footer></x-slot:footer>
</x-layouts.guest>
