@php
    $editing = $organization->exists;
    $cancelUrl = $editing ? route('admin.organizations.show', $organization) : route('admin.organizations.index');
@endphp

<x-layouts.app
    :title="$editing ? 'Ubah kelompok' : 'Tambah kelompok'"
    :description="$editing ? $organization->name : 'Isi data utama dulu; bagian lain bisa dilengkapi kemudian.'"
    :back="$cancelUrl"
    focus
>
    <form method="POST" action="{{ $editing ? route('admin.organizations.update', $organization) : route('admin.organizations.store') }}" class="space-y-5">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        @include('shared.organizations.form', ['canAdminister' => true])

        <x-form-actions sticky>
            <x-button variant="ghost" :href="$cancelUrl">Batal</x-button>
            <x-button type="submit">{{ $editing ? 'Simpan perubahan' : 'Simpan kelompok' }}</x-button>
        </x-form-actions>
    </form>
</x-layouts.app>
