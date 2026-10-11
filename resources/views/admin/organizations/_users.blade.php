@use('App\Support\Format')

{{-- Satu kelompok boleh punya beberapa akun; tombol "Tambah akun" ada di aksi header halaman detail. --}}
@if ($users->isEmpty())
    <x-empty-state title="Belum ada akun untuk kelompok ini" description="Buat akun agar pengurus kelompok bisa mengisi profil dan laporan bulanan. Satu kelompok boleh punya beberapa akun." />
@else
    <p class="mb-4 text-ink-soft">Semua akun aktif di bawah ini menerima notifikasi laporan kelompok ini.</p>

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
