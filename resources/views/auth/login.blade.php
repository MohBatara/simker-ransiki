<x-layouts.auth title="Masuk" :cover="$cover" :cover-credit="$coverCredit">
    <h1 class="text-page-title font-semibold text-ink">Masuk</h1>
    <p class="mt-1 text-ink-soft">Gunakan username atau email.</p>

    @if (session('status'))
        <x-alert tone="ok" class="mt-6">{{ session('status') }}</x-alert>
    @endif

    <form novalidate method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf
        <x-field label="Username atau email" for="login">
            <x-input name="login" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus />
        </x-field>
        <x-field label="Kata sandi" for="password">
            <x-password-input name="password" autocomplete="current-password" required />
        </x-field>
        <x-button type="submit" class="w-full" processing="Memeriksa…">Masuk</x-button>
    </form>

    <p class="mt-6 text-caption"><x-link :href="route('password.request')" class="font-normal">Lupa kata sandi?</x-link></p>
    <p class="mt-2 text-caption text-ink-soft">Akun tanpa email: hubungi Admin distrik untuk mengatur ulang kata sandi.</p>
    <p class="mt-2 text-caption"><x-link :href="url('/')" muted class="font-normal">Kembali ke beranda</x-link></p>
</x-layouts.auth>
