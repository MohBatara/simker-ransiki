{{-- Ganti kata sandi. $page = true di Akun saya (bar simpan menempel); di halaman wajib ganti (layout guest) tombol penuh. --}}
<form novalidate method="POST" action="{{ route('account.password.update') }}" class="space-y-4">
    @csrf
    @method('PUT')
    @if ($page ?? false)
        <x-admin.form-intro />
    @else
        <x-form-errors />
    @endif
    @if ($page ?? false)
        <x-admin.form-section title="Ganti kata sandi" description="Minimal 8 karakter dan berbeda dari kata sandi saat ini.">
            @include('account._password-fields')
        </x-admin.form-section>
        <x-admin.form-bar :cancel="route('account.password.edit')" submit="Simpan kata sandi" />
    @else
        @include('account._password-fields')
        <x-button type="submit" processing="Menyimpan…" @class(['w-full' => $fullWidth ?? false])>Simpan kata sandi</x-button>
    @endif
</form>
