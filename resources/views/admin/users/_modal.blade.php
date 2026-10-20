{{--
    Popup tambah/ubah akun (md). Dipakai daftar Pengguna (tambah), detail akun (ubah), dan detail kelompok (tambah akun
    kelompok dengan $presetOrganization: peran dan kelompok sudah ditetapkan). Validasi gagal: popup terbuka lagi lewat
    old('_modal'), isian lama tetap. Variabel: $user (baru atau yang diubah), $presetOrganization (opsional), $roles, $opds,
    $organizations (tidak perlu bila preset), $open (buka saat dimuat, mis. ?tambah=1).
--}}
@php
    $editing = $user->exists;
    $name = $editing ? 'user-edit' : 'user-create';
    $self = $editing && $user->is(auth()->user());
    $presetOrganization ??= null;
    $role = old('role', $user->role?->value ?? ($presetOrganization ? 'kelompok' : null));
    $failed = old('_modal') === $name;
    $formId = 'form-'.$name;
@endphp

<x-admin.modal
    :name="$name"
    :title="$editing ? 'Ubah akun' : ($presetOrganization ? 'Tambah akun kelompok' : 'Tambah akun')"
    :description="$presetOrganization ? $presetOrganization->name : ($editing ? $user->name : 'Pengguna membuat kata sandi sendiri lewat undangan email, atau memakai kata sandi sementara.')"
    :show="$failed || ($open ?? false)"
>
    <form novalidate id="{{ $formId }}" method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
          x-data="{ role: @js($role), email: @js((string) ($failed ? old('email') : $user->email)) }" class="space-y-5">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <input type="hidden" name="_modal" value="{{ $name }}">
        <x-admin.form-intro />

        <x-field label="Nama" for="name" required>
            <x-input name="name" :value="$user->name" maxlength="255" required />
        </x-field>
        <x-field label="Username" for="username" hint="Boleh nama singkat atau nomor HP. Dipakai untuk masuk; huruf kecil, angka, titik, garis bawah, atau tanda hubung." required>
            <x-input name="username" :value="$user->username" autocapitalize="none" autocomplete="off" spellcheck="false" maxlength="50" required
                x-data x-on:input="$el.value = $el.value.toLowerCase().replace(/\s+/g, '')" />
        </x-field>
        <div class="grid gap-5 md:grid-cols-2">
            <x-field label="Email" for="email" hint="Boleh dikosongkan; banyak pengurus kelompok tidak punya email.">
                <x-input name="email" type="email" :value="$user->email" maxlength="255" autocapitalize="none" x-model="email" />
            </x-field>
            <x-field label="Nomor telepon" for="phone" hint="Untuk mengirim info masuk lewat WhatsApp.">
                <x-input name="phone" type="tel" inputmode="tel" :value="$user->phone" maxlength="30" placeholder="08xx xxxx xxxx" autocomplete="tel" />
            </x-field>
        </div>
        @unless ($editing)
            <x-field label="Cara masuk pertama" for="access" hint="Undangan: tautan berlaku 72 jam, sekali pakai, untuk membuat kata sandi sendiri. Tanpa email, selalu kata sandi sementara.">
                <x-select name="access"
                    :options="['invite' => 'Kirim undangan lewat email', 'temporary' => 'Kata sandi sementara (ditampilkan sekali)']"
                    :selected="old('access', 'invite')"
                    x-bind:disabled="! email.trim()" />
            </x-field>
        @endunless

        @if ($presetOrganization)
            <input type="hidden" name="role" value="kelompok">
            <input type="hidden" name="organization_id" value="{{ $presetOrganization->id }}">
        @elseif ($self)
            <x-field label="Peran" hint="Peran akun sendiri tidak bisa diubah.">
                <p class="text-ink">{{ $user->role->label() }}</p>
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
    </form>

    <x-slot:footer>
        <x-button variant="secondary" x-on:click="requestClose()">Batal</x-button>
        <x-button type="submit" :form="$formId" processing="Menyimpan…">{{ $editing ? 'Simpan perubahan' : 'Simpan akun' }}</x-button>
    </x-slot:footer>
</x-admin.modal>
