@php
    // Pesan dari Policy (Response::deny) ditampilkan; pesan bawaan Laravel berbahasa Inggris diganti.
    $message = $exception->getMessage();
    if ($message === '' || $message === 'This action is unauthorized.') {
        $message = 'Akun Anda tidak punya akses ke halaman ini.';
    }
@endphp

<x-layouts.guest title="Akses ditolak" heading="Akses ditolak">
    <p class="text-ui text-ink-soft">{{ $message }}</p>
    @include('errors._actions')
    <x-slot:footer></x-slot:footer>
</x-layouts.guest>
