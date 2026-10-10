<x-layouts.app title="Akun Saya" :description="auth()->user()->name.' · '.auth()->user()->role->label()">
    <div class="max-w-lg space-y-6">
        <x-card title="Ganti kata sandi">
            @include('account._password-form')
        </x-card>

        <x-card title="Keluar" description="Keluar dari akun ini di perangkat yang sedang dipakai.">
            <form method="POST" action="{{ route('logout') }}" data-logout>
                @csrf
                <x-button variant="secondary" type="submit" icon="arrow-right-start-on-rectangle" processing="Keluar…">Keluar</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.app>
