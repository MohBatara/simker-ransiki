{{--
    Halaman form profil kelompok untuk Admin (tambah/ubah), OPD, dan Kelompok (ubah).
    Variabel: $organization, $options, $canAdminister, $action, $cancelUrl.
--}}
@php
    $editing = $organization->exists;
    $area = auth()->user()->role->area();
    $breadcrumbs = match (true) {
        $area === 'kelompok' => [['label' => 'Profil Kelompok', 'href' => route('kelompok.profile.show')], ['label' => 'Ubah profil']],
        $editing => [
            ['label' => $area === 'opd' ? 'Kelompok binaan' : 'Kelompok', 'href' => route($area.'.organizations.index')],
            ['label' => $organization->name, 'href' => route($area.'.organizations.show', $organization)],
            ['label' => 'Ubah profil'],
        ],
        default => [['label' => 'Kelompok', 'href' => route('admin.organizations.index')], ['label' => 'Tambah kelompok']],
    };
@endphp

<x-layouts.app
    :title="$editing ? 'Ubah profil kelompok' : 'Tambah kelompok'"
    :description="$editing ? $organization->name : 'Isi data utama dulu; bagian lain bisa dilengkapi kemudian.'"
    :back="$cancelUrl"
    :breadcrumbs="$breadcrumbs"
    focus
>
    <form method="POST" action="{{ $action }}" class="space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <x-admin.form-intro />

        <x-admin.form-layout nav>
            @include('shared.organizations.form')
        </x-admin.form-layout>

        <x-admin.form-bar :cancel="$cancelUrl" :submit="$editing ? 'Simpan perubahan' : 'Simpan kelompok'" />
    </form>
</x-layouts.app>
