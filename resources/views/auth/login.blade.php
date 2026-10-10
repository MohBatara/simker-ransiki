<x-layouts.guest title="Masuk" heading="Masuk" description="Gunakan username atau email.">
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-field label="Username atau email" for="login">
            <x-input name="login" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus />
        </x-field>
        <x-field label="Kata sandi" for="password">
            <x-input name="password" type="password" autocomplete="current-password" required />
        </x-field>
        <x-button type="submit" class="w-full" processing="Memeriksa…">Masuk</x-button>
    </form>
    <p class="mt-4 text-caption text-ink-soft">Lupa kata sandi? Hubungi Admin distrik.</p>
</x-layouts.guest>
