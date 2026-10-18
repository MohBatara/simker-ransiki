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
        'Email' => $user->email ?? '—',
        'Telepon' => $user->phone ?? '—',
        'Login terakhir' => $user->last_login_at ? Format::date($user->last_login_at) : 'Belum pernah',
        'Wajib ganti kata sandi' => $user->hasPendingInvitation() ? 'Belum, menunggu undangan diterima' : ($user->must_change_password ? 'Ya, saat masuk berikutnya' : 'Tidak'),
        'Dibuat' => Format::date($user->created_at),
    ];
@endphp

<x-layouts.app
    :title="$user->name"
    :description="$user->role->label()"
    :back="route('admin.users.index')"
    :breadcrumbs="[['label' => 'Pengaturan', 'href' => route('admin.users.index')], ['label' => 'Pengguna', 'href' => route('admin.users.index')], ['label' => $user->name]]"
>
    <x-slot:actions>
        <x-button variant="secondary" icon="pencil-square" :href="route('admin.users.edit', $user)">Ubah</x-button>
    </x-slot:actions>

    <div class="max-w-2xl space-y-5">
        @if ($loginInfo)
            <x-alert title="Kata sandi sementara">
                <p>Kata sandi ini hanya ditampilkan sekali. Salin lalu kirim ke pengguna, mis. lewat WhatsApp. Pengguna wajib menggantinya saat pertama masuk.</p>
                <dl class="mt-3 space-y-1">
                    <div class="flex flex-wrap gap-x-2"><dt class="text-ink-soft">Alamat:</dt><dd class="break-all">{{ route('login') }}</dd></div>
                    <div class="flex flex-wrap gap-x-2"><dt class="text-ink-soft">Username:</dt><dd class="font-semibold">{{ $user->username }}</dd></div>
                    <div class="flex flex-wrap gap-x-2"><dt class="text-ink-soft">Kata sandi sementara:</dt><dd class="font-semibold tracking-wide tabular-nums" data-temporary-password>{{ $temporaryPassword }}</dd></div>
                </dl>
                <div class="mt-4" x-data="copyText(@js($loginInfo))">
                    <x-button x-on:click="copy()" icon="clipboard-document">
                        <span x-text="copied ? 'Info masuk disalin' : 'Salin info masuk'">Salin info masuk</span>
                    </x-button>
                </div>
            </x-alert>
        @endif

        <div class="flex flex-wrap gap-2">
            <x-badge :tone="$user->is_active ? 'ok' : 'idle'">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
        </div>

        <x-card title="Data akun">
            <dl class="divide-y divide-line">
                @foreach (array_filter($rows, fn ($value) => $value !== null) as $label => $value)
                    <div class="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,12rem)_1fr] sm:gap-4">
                        <dt class="text-ink-soft">{{ $label }}</dt>
                        <dd>
                            @if ($label === 'Kelompok')
                                <a href="{{ route('admin.organizations.show', $user->organization) }}" class="text-brand underline">{{ $value }}</a>
                            @else
                                {{ $value }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        @if ($user->hasPendingInvitation())
            <x-card title="Undangan email" :description="'Undangan dikirim '.Format::dateTime($user->invited_at).' ke '.$user->email.'. Tautan berlaku 72 jam dan hanya sekali pakai.'">
                @can('invite', $user)
                    <form method="POST" action="{{ route('admin.users.invitation', $user) }}">
                        @csrf
                        <x-button variant="secondary" type="submit" icon="envelope" processing="Mengirim…">Kirim ulang undangan</x-button>
                    </form>
                    <p class="mt-2 text-caption text-ink-muted">Tautan lama otomatis tidak berlaku lagi.</p>
                @endcan
            </x-card>
        @endif

        <x-card title="Kata sandi" description="Buat kata sandi sementara baru bila pengguna lupa kata sandinya. Pengguna yang punya email juga bisa memakai Lupa kata sandi? di halaman masuk.">
            <x-button variant="secondary" icon="key" x-data x-on:click="$dispatch('open-modal', 'reset-password')">Reset kata sandi</x-button>
        </x-card>

        <x-card title="Status akun">
            @if ($self)
                <p class="text-ink-soft">Anda tidak bisa menonaktifkan akun sendiri.</p>
            @elseif ($user->is_active)
                <p class="text-ink-soft">Akun nonaktif tidak bisa masuk dan langsung dikeluarkan bila sedang masuk.</p>
                <x-button variant="danger" class="mt-4" x-data x-on:click="$dispatch('open-modal', 'deactivate-user')">Nonaktifkan akun</x-button>
            @else
                <p class="text-ink-soft">Akun ini nonaktif dan tidak bisa masuk.</p>
                <form method="POST" action="{{ route('admin.users.activate', $user) }}" class="mt-4">
                    @csrf
                    @method('PATCH')
                    <x-button variant="secondary" type="submit">Aktifkan akun</x-button>
                </form>
            @endif
        </x-card>
    </div>

    <x-modal name="reset-password" title="Reset kata sandi">
        <p>Kata sandi lama <strong class="text-ink">{{ $user->name }}</strong> tidak berlaku lagi dan pengguna dikeluarkan dari semua perangkat. Kata sandi sementara baru akan ditampilkan sekali.</p>
        <x-slot:footer>
            <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'reset-password')">Batal</x-button>
            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                @csrf
                <x-button type="submit">Reset kata sandi</x-button>
            </form>
        </x-slot:footer>
    </x-modal>

    @if (! $self && $user->is_active)
        <x-modal name="deactivate-user" title="Nonaktifkan akun">
            <p><strong class="text-ink">{{ $user->name }}</strong> tidak bisa masuk sampai akunnya diaktifkan kembali. Data dan laporan yang sudah dibuat tetap tersimpan.</p>
            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'deactivate-user')">Batal</x-button>
                <form method="POST" action="{{ route('admin.users.deactivate', $user) }}">
                    @csrf
                    @method('PATCH')
                    <x-button variant="danger" type="submit">Nonaktifkan</x-button>
                </form>
            </x-slot:footer>
        </x-modal>
    @endif
</x-layouts.app>
