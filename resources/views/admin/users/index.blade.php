@use('App\Support\Format')

@php
    $url = route('admin.users.index');
    $active = collect($filters)->only(['role', 'status'])->filter(fn ($value) => filled($value))->count();
    $chips = \App\Support\FilterChips::from($filters, [
        'q' => [null, null],
        'role' => ['Peran', App\Enums\Role::options()],
        'status' => ['Status', ['active' => 'Aktif', 'inactive' => 'Nonaktif']],
    ]);
    $hasFilters = $chips !== [];
    $summary = collect([$users->total().' akun', $inactiveCount ? $inactiveCount.' nonaktif' : null])->filter()->join(' · ');
@endphp

<x-layouts.app title="Pengguna" description="Akun Admin, petugas OPD, dan pengurus kelompok." section="pengaturan" tab="pengguna">
    <x-slot:actions>
        <x-button icon="plus" :href="route('admin.users.create')">Tambah akun</x-button>
    </x-slot:actions>

    <div class="space-y-4">
        <x-filter-bar :action="$url" search-label="Cari nama, username, atau email" :search="$filters['q'] ?? ''" :active="$active">
            <x-select size="sm" name="role" :options="App\Enums\Role::options()" :selected="$filters['role'] ?? null" placeholder="Semua peran" aria-label="Peran" x-on:change="$el.form.requestSubmit()" />
            <x-select size="sm" name="status" :options="['active' => 'Aktif', 'inactive' => 'Nonaktif']" :selected="$filters['status'] ?? null" placeholder="Semua status" aria-label="Status akun" x-on:change="$el.form.requestSubmit()" />
        </x-filter-bar>

        <x-filter-summary :summary="$summary" :chips="$chips" :clear-url="$url" />

        @if ($users->isEmpty())
            @if ($hasFilters)
                <x-empty-state title="Tidak ada akun yang cocok" description="Ubah kata pencarian atau hapus filter.">
                    <x-button variant="secondary" :href="route('admin.users.index')">Hapus filter</x-button>
                </x-empty-state>
            @else
                <x-empty-state title="Belum ada akun" description="Buat akun untuk petugas OPD dan pengurus kelompok.">
                    <x-button icon="plus" :href="route('admin.users.create')">Tambah akun</x-button>
                </x-empty-state>
            @endif
        @else
            <x-table clickable>
                <x-slot:head>
                    <x-table.heading>Nama</x-table.heading>
                    <x-table.heading>Username</x-table.heading>
                    <x-table.heading>Peran</x-table.heading>
                    <x-table.heading optional>Login terakhir</x-table.heading>
                    <x-table.heading>Status</x-table.heading>
                </x-slot:head>

                @foreach ($users as $user)
                    <x-table.row :href="route('admin.users.show', $user)">
                        <x-table.cell primary>
                            <span class="flex items-center gap-3">
                                <x-avatar :name="$user->name" size="sm" />
                                <span class="min-w-0">{{ $user->name }}</span>
                            </span>
                        </x-table.cell>
                        <x-table.cell subtitle>{{ $user->username }}</x-table.cell>
                        <x-table.cell label="Peran">
                            {{ $user->role->label() }}@if ($user->opd) · {{ $user->opd->short_name }}@elseif ($user->organization) · {{ $user->organization->name }}@endif
                        </x-table.cell>
                        <x-table.cell label="Login terakhir" optional>{{ $user->last_login_at ? Format::date($user->last_login_at) : 'Belum pernah' }}</x-table.cell>
                        <x-table.cell badge>
                            <x-badge :tone="$user->is_active ? 'ok' : 'idle'">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                        </x-table.cell>
                    </x-table.row>
                @endforeach

                <x-slot:footer>
                    <div class="space-y-3">
                        <p class="text-caption text-ink-muted tabular-nums">
                            Menampilkan {{ $users->firstItem() }}–{{ $users->lastItem() }} dari {{ $users->total() }} akun
                        </p>
                        {{ $users->links() }}
                    </div>
                </x-slot:footer>
            </x-table>
        @endif
    </div>
</x-layouts.app>
