{{--
    Halaman form profil kelompok untuk Admin (tambah/ubah), OPD, dan Kelompok (ubah).
    Variabel: $organization, $options, $canAdminister, $action, $cancelUrl.
--}}
@php
    $editing = $organization->exists;
@endphp

<x-layouts.app
    :title="$editing ? 'Ubah profil kelompok' : 'Tambah kelompok'"
    :description="$editing ? $organization->name : 'Isi data utama dulu; bagian lain bisa dilengkapi kemudian.'"
    :back="$cancelUrl"
    focus
>
    <form method="POST" action="{{ $action }}" class="space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <x-form-errors />

        @include('shared.organizations.form')

        <x-admin.form-bar :cancel="$cancelUrl" :submit="$editing ? 'Simpan perubahan' : 'Simpan kelompok'" />
    </form>
</x-layouts.app>
