@use('App\Support\Format')

@php
    $url = route('admin.users.index');
    $chips = \App\Support\FilterChips::from($filters, [
        'q' => [null, null],
        'role' => ['Peran', App\Enums\Role::options()],
        'status' => ['Status', ['active' => 'Aktif', 'inactive' => 'Nonaktif']],
    ]);
    $hasFilters = $chips !== [];
    $summary = collect([$users->total().' akun', $inactiveCount ? $inactiveCount.' nonaktif' : null])->filter()->join(', ');
    $toolbarFilters = [
        ['name' => 'role', 'label' => 'Peran', 'options' => App\Enums\Role::options(), 'selected' => $filters['role'] ?? null, 'placeholder' => 'Semua peran'],
        ['name' => 'status', 'label' => 'Status akun', 'options' => ['active' => 'Aktif', 'inactive' => 'Nonaktif'], 'selected' => $filters['status'] ?? null, 'placeholder' => 'Semua status'],
    ];
@endphp

<x-layouts.app title="Pengguna" description="Akun Admin, petugas OPD, dan pengurus kelompok." section="pengaturan" tab="pengguna">
    <x-slot:actions>
        <x-button icon="plus" :href="route('admin.users.create')">Tambah akun</x-button>
    </x-slot:actions>

    <x-admin.card flush>
        <x-admin.toolbar
            title="Daftar akun"
            :action="$url"
            search-label="Cari nama, username, atau email"
            :search="$filters['q'] ?? ''"
            :filters="$toolbarFilters"
            :chips="$chips"
            :summary="$summary"
            :clear-url="$hasFilters ? $url : null"
        />

        {{-- Ditukar oleh x-admin.toolbar saat filter berubah. --}}
        <div data-swap="pengguna">
            @if ($users->isEmpty())
                @if ($hasFilters)
                    <x-admin.empty bare icon="magnifying-glass" title="Tidak ada akun yang cocok" description="Ubah kata pencarian atau hapus filter.">
                        <x-button variant="secondary" :href="$url">Hapus filter</x-button>
                    </x-admin.empty>
                @else
                    <x-admin.empty bare icon="users" title="Belum ada akun" description="Buat akun untuk petugas OPD dan pengurus kelompok.">
                        <x-button icon="plus" :href="route('admin.users.create')">Tambah akun</x-button>
                    </x-admin.empty>
                @endif
            @else
                <x-admin.table bare clickable>
                    <x-slot:head>
                        <x-admin.table.heading>Nama</x-admin.table.heading>
                        <x-admin.table.heading fit>Peran</x-admin.table.heading>
                        <x-admin.table.heading fit optional>Login terakhir</x-admin.table.heading>
                        <x-admin.table.heading fit>Status</x-admin.table.heading>
                    </x-slot:head>

                    @foreach ($users as $user)
                        <x-admin.table.row :href="route('admin.users.show', $user)" :edit="route('admin.users.edit', $user)" :for="$user->name">
                            <x-admin.table.cell primary>
                                <span class="flex items-center gap-3">
                                    <x-avatar :name="$user->name" size="sm" />
                                    <span class="min-w-0">
                                        <span class="block">{{ $user->name }}</span>
                                        <span class="block text-label font-normal text-ink-3">{{ $user->username }}</span>
                                    </span>
                                </span>
                            </x-admin.table.cell>
                            <x-admin.table.cell label="Peran" fit>
                                <x-admin.chip :icon="match ($user->role) { App\Enums\Role::Admin => 'shield-check', App\Enums\Role::Opd => 'briefcase', default => 'user-group' }">{{ $user->role->label() }}</x-admin.chip>
                                @if ($user->opd || $user->organization)
                                    <span class="mt-1 block text-label text-ink-3">{{ $user->opd?->short_name ?? $user->organization->name }}</span>
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell label="Login terakhir" fit optional>
                                @if ($user->last_login_at)<x-admin.date :value="$user->last_login_at" time />@else Belum pernah @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell badge>
                                <x-admin.badge-status :tone="$user->is_active ? 'success' : 'neutral'" :label="$user->is_active ? 'Aktif' : 'Nonaktif'" />
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach

                    <x-slot:footer>
                        <x-admin.pagination :paginator="$users" noun="akun" />
                    </x-slot:footer>
                </x-admin.table>
            @endif
        </div>
    </x-admin.card>
</x-layouts.app>
