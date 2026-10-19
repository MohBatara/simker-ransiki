@php
    $editing = $user->exists;
    $self = $editing && $user->is(auth()->user());
    $role = old('role', $user->role?->value);
    $cancelUrl = match (true) {
        $editing => route('admin.users.show', $user),
        (bool) $presetOrganization => route('admin.organizations.users', $presetOrganization),
        default => route('admin.users.index'),
    };
@endphp

<x-layouts.app
    :title="$editing ? 'Ubah akun' : 'Tambah akun'"
    :description="$editing ? $user->name : 'Pengguna membuat kata sandi sendiri lewat undangan email, atau memakai kata sandi sementara.'"
    :back="$cancelUrl"
    :breadcrumbs="$editing
        ? [['label' => 'Pengguna', 'href' => route('admin.users.index')], ['label' => $user->name, 'href' => route('admin.users.show', $user)], ['label' => 'Ubah akun']]
        : [['label' => 'Pengguna', 'href' => route('admin.users.index')], ['label' => 'Tambah akun']]"
    focus
>
    <form novalidate method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" x-data="{ role: @js($role), email: @js((string) old('email', $user->email)) }" class="space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <x-admin.form-intro />

        <x-admin.form-section title="Data akun" description="Nama dan cara masuk. Username dipakai untuk masuk dan tidak boleh sama dengan akun lain.">
            <div class="grid gap-5 md:grid-cols-2">
                <x-field label="Nama" for="name" class="md:col-span-2" required>
                    <x-input name="name" :value="$user->name" maxlength="255" required />
                </x-field>
                <x-field label="Username" for="username" hint="Boleh nama singkat atau nomor HP. Dipakai untuk masuk; huruf kecil, angka, titik, garis bawah, atau tanda hubung." required>
                    <x-input
                        name="username"
                        :value="$user->username"
                        autocapitalize="none"
                        autocomplete="off"
                        spellcheck="false"
                        maxlength="50"
                        required
                        x-data
                        x-on:input="$el.value = $el.value.toLowerCase().replace(/\s+/g, '')"
                    />
                </x-field>
                <x-field label="Email" for="email" hint="Banyak pengurus kelompok tidak punya email; boleh dikosongkan." optional>
                    <x-input name="email" type="email" :value="$user->email" maxlength="255" autocapitalize="none" x-model="email" />
                </x-field>
                @unless ($editing)
                    <x-field label="Cara masuk pertama" for="access" class="md:col-span-2" hint="Undangan: pengguna menerima tautan (berlaku 72 jam, sekali pakai) untuk membuat kata sandi sendiri. Tanpa email, selalu kata sandi sementara.">
                        <x-select
                            name="access"
                            :options="['invite' => 'Kirim undangan lewat email', 'temporary' => 'Kata sandi sementara (ditampilkan sekali)']"
                            :selected="old('access', 'invite')"
                            x-bind:disabled="! email.trim()"
                        />
                    </x-field>
                @endunless
                <x-field label="Nomor telepon" for="phone" hint="Untuk mengirim info masuk lewat WhatsApp." optional>
                    <x-input name="phone" type="tel" inputmode="tel" :value="$user->phone" maxlength="30" placeholder="08xx xxxx xxxx" autocomplete="tel" class="md:max-w-60" />
                </x-field>
            </div>
        </x-admin.form-section>

        <x-admin.form-section title="Peran" description="Peran menentukan menu dan data yang bisa dibuka akun ini.">
            <div class="grid gap-5 md:grid-cols-2">
                @if ($self)
                    <x-field label="Peran" hint="Peran akun sendiri tidak bisa diubah.">
                        <p>{{ $user->role->label() }}</p>
                    </x-field>
                @else
                    <x-field label="Peran" for="role" required>
                        <x-select name="role" :options="$roles" :selected="$role" placeholder="Pilih peran" x-model="role" required />
                    </x-field>

                    <x-field label="OPD" for="opd_id" required x-show="role === 'opd'" :x-cloak="$role !== 'opd'">
                        <x-select name="opd_id" :options="$opds" :selected="$user->opd_id" placeholder="Pilih OPD" />
                    </x-field>

                    <x-field label="Kelompok" for="organization_id" required hint="Satu kelompok boleh punya lebih dari satu akun." x-show="role === 'kelompok'" :x-cloak="$role !== 'kelompok'">
                        <x-select name="organization_id" :options="$organizations" :selected="$user->organization_id" placeholder="Pilih kelompok" />
                    </x-field>
                @endif
            </div>
        </x-admin.form-section>

        <x-admin.form-bar :cancel="$cancelUrl" :submit="$editing ? 'Simpan perubahan' : 'Simpan akun'" />
    </form>
</x-layouts.app>
