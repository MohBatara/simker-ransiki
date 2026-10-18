@use('App\Support\Format')

{{-- Satu kelompok boleh punya beberapa akun; tombol "Tambah akun" ada di aksi header halaman detail. --}}
@if ($users->isEmpty())
    <x-admin.empty title="Belum ada akun untuk kelompok ini" description="Buat akun agar pengurus kelompok bisa mengisi profil dan laporan bulanan. Satu kelompok boleh punya beberapa akun." />
@else
    <p class="mb-4 text-ink-soft">Semua akun aktif di bawah ini menerima notifikasi laporan kelompok ini.</p>

    <x-admin.table clickable>
        <x-slot:head>
            <x-admin.table.heading>Nama</x-admin.table.heading>
            <x-admin.table.heading>Username</x-admin.table.heading>
            <x-admin.table.heading>Status</x-admin.table.heading>
            <x-admin.table.heading>Login terakhir</x-admin.table.heading>
        </x-slot:head>

        @foreach ($users as $user)
            <x-admin.table.row :href="route('admin.users.show', $user)">
                <x-admin.table.cell primary>{{ $user->name }}</x-admin.table.cell>
                <x-admin.table.cell subtitle>{{ $user->username }}</x-admin.table.cell>
                <x-admin.table.cell badge>
                    <x-admin.badge-status :tone="$user->is_active ? 'success' : 'neutral'" :label="$user->is_active ? 'Aktif' : 'Nonaktif'" />
                </x-admin.table.cell>
                <x-admin.table.cell label="Login terakhir">@if ($user->last_login_at)<x-admin.date :value="$user->last_login_at" time />@else Belum pernah @endif</x-admin.table.cell>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>
@endif
