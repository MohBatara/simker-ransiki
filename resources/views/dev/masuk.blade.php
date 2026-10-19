<x-layouts.guest title="Contoh layout masuk" heading="Masuk" description="Gunakan username atau email.">
    <form novalidate class="space-y-4" x-on:submit.prevent>
        <x-field label="Username atau email" for="login">
            <x-input name="login" autocomplete="username" />
        </x-field>
        <x-field label="Kata sandi" for="password">
            <x-input name="password" type="password" autocomplete="current-password" />
        </x-field>
        <x-checkbox name="remember">Ingat saya</x-checkbox>
        <x-button type="submit" class="w-full">Masuk</x-button>
    </form>
</x-layouts.guest>
