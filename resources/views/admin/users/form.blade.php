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
    :description="$editing ? $user->name : 'Kata sandi sementara dibuat otomatis setelah akun disimpan.'"
    :back="$cancelUrl"
    focus
>
    <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" x-data="{ role: @js($role) }" class="space-y-5">
        @csrf
        <x-form-errors />
        @if ($editing)
            @method('PUT')
        @endif

        <x-card title="Data akun">
            <div class="grid gap-5 md:grid-cols-2">
                <x-field label="Nama" for="name" class="md:col-span-2">
                    <x-input name="name" :value="$user->name" maxlength="255" required />
                </x-field>
                <x-field label="Username" for="username" hint="Dipakai untuk masuk. Huruf kecil, angka, titik, garis bawah, atau tanda hubung.">
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
                    <x-input name="email" type="email" :value="$user->email" maxlength="255" autocapitalize="none" />
                </x-field>
                <x-field label="Nomor telepon" for="phone" hint="Untuk mengirim info masuk lewat WhatsApp." optional>
                    <x-input name="phone" type="tel" inputmode="tel" :value="$user->phone" maxlength="30" />
                </x-field>
            </div>
        </x-card>

        <x-card title="Peran">
            <div class="grid gap-5 md:grid-cols-2">
                @if ($self)
                    <x-field label="Peran" hint="Peran akun sendiri tidak bisa diubah.">
                        <p>{{ $user->role->label() }}</p>
                    </x-field>
                @else
                    <x-field label="Peran" for="role">
                        <x-select name="role" :options="$roles" :selected="$role" placeholder="Pilih peran" x-model="role" required />
                    </x-field>

                    <x-field label="OPD" for="opd_id" x-show="role === 'opd'" :x-cloak="$role !== 'opd'">
                        <x-select name="opd_id" :options="$opds" :selected="$user->opd_id" placeholder="Pilih OPD" />
                    </x-field>

                    <x-field label="Kelompok" for="organization_id" hint="Satu kelompok boleh punya lebih dari satu akun." x-show="role === 'kelompok'" :x-cloak="$role !== 'kelompok'">
                        <x-select name="organization_id" :options="$organizations" :selected="$user->organization_id" placeholder="Pilih kelompok" />
                    </x-field>
                @endif
            </div>
        </x-card>

        <x-form-actions sticky>
            <x-button variant="ghost" :href="$cancelUrl">Batal</x-button>
            <x-button type="submit" processing="Menyimpan…">{{ $editing ? 'Simpan perubahan' : 'Simpan akun' }}</x-button>
        </x-form-actions>
    </form>
</x-layouts.app>
