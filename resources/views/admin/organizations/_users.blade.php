@use('App\Support\Format')

@if ($users->isEmpty())
    <x-empty-state title="Belum ada akun untuk kelompok ini" description="Akun kelompok dibuat Admin lewat menu Pengguna." />
@else
    <x-table>
        <x-slot:head>
            <x-table.heading>Nama</x-table.heading>
            <x-table.heading>Username</x-table.heading>
            <x-table.heading>Status</x-table.heading>
            <x-table.heading>Login terakhir</x-table.heading>
        </x-slot:head>

        @foreach ($users as $user)
            <x-table.row>
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
