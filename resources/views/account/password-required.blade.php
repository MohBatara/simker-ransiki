<x-layouts.guest
    title="Ganti kata sandi"
    heading="Ganti kata sandi"
    description="Akun ini memakai kata sandi sementara. Buat kata sandi baru sebelum melanjutkan."
>
    @include('account._password-form', ['fullWidth' => true])

    <form method="POST" action="{{ route('logout') }}" data-logout class="mt-3">
        @csrf
        <x-button variant="ghost" type="submit" class="w-full" processing="Keluar…">Keluar</x-button>
    </form>
</x-layouts.guest>
