<x-layouts.guest title="Sedang diperbarui" heading="Sedang diperbarui">
    <p class="text-ui text-ink-soft">SIM-KER sedang diperbarui dan untuk sementara tidak bisa dibuka. Coba lagi beberapa menit lagi.</p>
    @include('errors._actions', ['reload' => true])
    <x-slot:footer></x-slot:footer>
</x-layouts.guest>
