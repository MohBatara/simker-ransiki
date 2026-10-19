@use('App\Support\Format')

@php
    $self = $user->is(auth()->user());
    $loginInfo = $temporaryPassword
        ? 'Alamat: '.route('login').', Username: '.$user->username.', Kata sandi sementara: '.$temporaryPassword
        : null;
    $rows = [
        'Username' => $user->username,
        'Peran' => $user->role->label(),
        'OPD' => $user->opd?->name,
        'Kelompok' => $user->organization?->name,
        'Email' => $user->email ?? \App\Support\Format::orEmpty(null),
        'Telepon' => $user->phone ?? \App\Support\Format::orEmpty(null),
        'Login terakhir' => $user->last_login_at ? Format::dateTime($user->last_login_at).' WIT' : 'Belum pernah',
        'Wajib ganti kata sandi' => $user->hasPendingInvitation() ? 'Belum, menunggu undangan diterima' : ($user->must_change_password ? 'Ya, saat masuk berikutnya' : 'Tidak'),
        'Dibuat' => Format::date($user->created_at),
    ];
@endphp

<x-layouts.app
    :title="$user->name"
    :description="$user->role->label().($user->opd ? ', '.$user->opd->name : '').($user->organization ? ', '.$user->organization->name : '')"
    :back="route('admin.users.index')"
    phone-title="Detail akun"
    :breadcrumbs="[['label' => 'Pengaturan', 'href' => route('admin.users.index')], ['label' => 'Pengguna', 'href' => route('admin.users.index')], ['label' => $user->name]]"
>
    <x-slot:badge>
        <x-admin.badge-status :tone="$user->is_active ? 'success' : 'neutral'" :label="$user->is_active ? 'Aktif' : 'Nonaktif'" />
    </x-slot:badge>
    <x-slot:secondary>
        <x-button variant="secondary" icon="key" x-data x-on:click="$dispatch('open-modal', 'reset-password')">Atur ulang sandi</x-button>
        @if ($user->hasPendingInvitation())
            @can('invite', $user)
                <x-button variant="secondary" icon="envelope" type="submit" form="form-invitation" processing="Mengirim…">Kirim ulang undangan</x-button>
            @endcan
        @endif
    </x-slot:secondary>
    <x-slot:actions>
        <x-button icon="pencil-square" :href="route('admin.users.edit', $user)">Ubah akun</x-button>
    </x-slot:actions>

    @if ($user->hasPendingInvitation())
        @can('invite', $user)
            <form id="form-invitation" method="POST" action="{{ route('admin.users.invitation', $user) }}" class="hidden">@csrf</form>
        @endcan
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div class="min-w-0 space-y-6">
            @if ($loginInfo)
                <x-admin.card title="Kata sandi sementara" description="Hanya ditampilkan sekali. Salin lalu kirim ke pengguna, mis. lewat WhatsApp. Pengguna wajib menggantinya saat pertama masuk." class="border-wait/40! bg-wait-bg!">
                    <dl class="space-y-1">
                        <div class="flex flex-wrap gap-x-2"><dt class="text-ink-soft">Alamat:</dt><dd class="break-all">{{ route('login') }}</dd></div>
                        <div class="flex flex-wrap gap-x-2"><dt class="text-ink-soft">Username:</dt><dd class="font-semibold">{{ $user->username }}</dd></div>
                        <div class="flex flex-wrap gap-x-2"><dt class="text-ink-soft">Kata sandi sementara:</dt><dd class="font-semibold tracking-wide tabular-nums" data-temporary-password>{{ $temporaryPassword }}</dd></div>
                    </dl>
                    <div class="mt-4" x-data="copyText(@js($loginInfo))">
                        <x-button x-on:click="copy()" icon="clipboard-document">
                            <span x-text="copied ? 'Info masuk disalin' : 'Salin info masuk'">Salin info masuk</span>
                        </x-button>
                    </div>
                </x-admin.card>
            @endif

            <x-admin.card title="Data akun" flush>
                <dl class="divide-y divide-line">
                    @foreach (array_filter($rows, fn ($value) => $value !== null) as $label => $value)
                        <div class="grid gap-1 px-4 py-3 sm:grid-cols-[minmax(0,12rem)_1fr] sm:gap-4 md:px-5">
                            <dt class="text-ink-soft">{{ $label }}</dt>
                            <dd class="min-w-0 break-words text-ink">
                                @if ($label === 'Kelompok')
                                    <x-link :href="route('admin.organizations.show', $user->organization)" inline>{{ \App\Support\Format::orEmpty($value) }}</x-link>
                                @else
                                    {{ \App\Support\Format::orEmpty($value) }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-admin.card>
        </div>

        <div class="space-y-6">
            @if ($user->hasPendingInvitation())
                <x-admin.card title="Undangan email">
                    <p class="text-ui text-ink-soft">Dikirim {{ \App\Support\Format::orEmpty(Format::dateTime($user->invited_at)) }} WIT ke {{ $user->email }}. Tautan berlaku 72 jam dan hanya sekali pakai; mengirim ulang membatalkan tautan lama.</p>
                </x-admin.card>
            @endif

            <x-admin.card title="Kata sandi">
                <p class="text-ui text-ink-soft">Buat kata sandi sementara baru bila pengguna lupa kata sandinya. Pengguna yang punya email juga bisa memakai Lupa kata sandi? di halaman masuk.</p>
            </x-admin.card>

            <x-admin.card title="Status akun">
                @if ($self)
                    <p class="text-ui text-ink-soft">Anda tidak bisa menonaktifkan akun sendiri.</p>
                @elseif ($user->is_active)
                    <p class="text-ui text-ink-soft">Akun nonaktif tidak bisa masuk dan langsung dikeluarkan bila sedang masuk.</p>
                    <x-button variant="danger" class="mt-4" x-data x-on:click="$dispatch('open-modal', 'deactivate-user')">Nonaktifkan akun</x-button>
                @else
                    <p class="text-ui text-ink-soft">Akun ini nonaktif dan tidak bisa masuk.</p>
                    <form method="POST" action="{{ route('admin.users.activate', $user) }}" class="mt-4">
                        @csrf
                        @method('PATCH')
                        <x-button variant="secondary" type="submit">Aktifkan akun</x-button>
                    </form>
                @endif
            </x-admin.card>
        </div>
    </div>

    <x-admin.confirm name="reset-password" title="Atur ulang kata sandi" :action="route('admin.users.reset-password', $user)" confirm="Atur ulang" tone="brand">
        <p>Kata sandi lama <strong class="text-ink">{{ $user->name }}</strong> tidak berlaku lagi dan pengguna dikeluarkan dari semua perangkat. Kata sandi sementara baru akan ditampilkan sekali.</p>
    </x-admin.confirm>

    @if (! $self && $user->is_active)
        <x-admin.confirm name="deactivate-user" title="Nonaktifkan akun" :action="route('admin.users.deactivate', $user)" method="PATCH" confirm="Nonaktifkan">
            <p><strong class="text-ink">{{ $user->name }}</strong> tidak bisa masuk sampai akunnya diaktifkan kembali. Data dan laporan yang sudah dibuat tetap tersimpan.</p>
        </x-admin.confirm>
    @endif
</x-layouts.app>
