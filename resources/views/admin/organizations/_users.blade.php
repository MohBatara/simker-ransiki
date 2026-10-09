@use('App\Support\Format')

@php
    $createUrl = route('admin.users.create', ['kelompok' => $organization->id]);
@endphp

@if ($users->isEmpty())
    <x-empty-state title="Belum ada akun untuk kelompok ini" description="Buat akun agar pengurus kelompok bisa mengisi profil dan laporan bulanan.">
        @can('create', App\Models\User::class)
            <x-button icon="plus" :href="$createUrl">Tambah akun untuk kelompok ini</x-button>
        @endcan
    </x-empty-state>
@else
    @can('create', App\Models\User::class)
        <div class="mb-4 flex justify-end">
            <x-button icon="plus" :href="$createUrl">Tambah akun untuk kelompok ini</x-button>
        </div>
    @endcan

    <x-table clickable>
        <x-slot:head>
            <x-table.heading>Nama</x-table.heading>
            <x-table.heading>Username</x-table.heading>
            <x-table.heading>Status</x-table.heading>
            <x-table.heading>Login terakhir</x-table.heading>
        </x-slot:head>

        @foreach ($users as $user)
            <x-table.row :href="route('admin.users.show', $user)">
                <x-table.cell primary>{{ $user->name }}</x-table.cell>
                <x-table.cell subtitle>{{ $user->username }}</x-table.cell>
                <x-table.cell badge>
                    <x-badge :tone="$user->is_active ? 'ok' : 'idle'">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                </x-table.cell>
                <x-table.cell label="Login terakhir">{{ $user->last_login_at ? Format::date($user->last_login_at) : 'Belum pernah' }}</x-table.cell>
            </x-table.row>
        @endforeach
    </x-table>
@endif
