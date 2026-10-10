<x-layouts.guest title="Sesi berakhir" heading="Sesi berakhir">
    <p class="text-ui text-ink-soft">Halaman terlalu lama dibiarkan terbuka, sehingga kiriman tidak diterima. Kembali ke halaman sebelumnya, muat ulang halaman itu, lalu ulangi langkah Anda.</p>
    @include('errors._actions', ['login' => true])
    <x-slot:footer></x-slot:footer>
</x-layouts.guest>
