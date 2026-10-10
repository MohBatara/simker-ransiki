<x-layouts.auth title="Masuk" :cover="$cover" :cover-credit="$coverCredit">
    <h1 class="text-page-title font-semibold text-ink">Masuk</h1>
    <p class="mt-1 text-ink-soft">Gunakan username atau email.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf
        <x-field label="Username atau email" for="login">
            <x-input name="login" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus />
        </x-field>
        <x-field label="Kata sandi" for="password">
            <x-password-input name="password" autocomplete="current-password" required />
        </x-field>
        <x-button type="submit" class="w-full" processing="Memeriksa…">Masuk</x-button>
    </form>

    <p class="mt-6 text-caption text-ink-soft">Lupa kata sandi? Hubungi Admin distrik.</p>
    <p class="mt-2 text-caption"><x-link href="{{ url('/') }}" muted class="font-normal">Kembali ke beranda</x-link></p>
</x-layouts.auth>
