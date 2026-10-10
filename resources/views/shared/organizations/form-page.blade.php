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
    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @if ($editing)
        <x-form-errors />
            @method('PUT')
        @endif

        @include('shared.organizations.form')

        <x-form-actions sticky>
            <x-button variant="ghost" :href="$cancelUrl">Batal</x-button>
            <x-button type="submit" processing="Menyimpan…">{{ $editing ? 'Simpan perubahan' : 'Simpan kelompok' }}</x-button>
        </x-form-actions>
    </form>
</x-layouts.app>
