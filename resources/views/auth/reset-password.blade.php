<x-layouts.auth title="Atur ulang kata sandi" :cover="$cover" :cover-credit="$coverCredit">
    <h1 class="text-page-title font-semibold text-ink">Atur ulang kata sandi</h1>
    <p class="mt-1 text-ink-soft">Buat kata sandi baru untuk akun Anda.</p>

    @if ($current)
        <x-alert tone="wait" class="mt-6">Browser ini sedang masuk sebagai {{ $current->name }}. Saat kata sandi disimpan, akun itu dikeluarkan.</x-alert>
    @endif

    <form novalidate method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form-errors />
        <x-field label="Email" for="email">
            <x-input name="email" type="email" :value="$email" autocomplete="email" autocapitalize="none" spellcheck="false" required />
        </x-field>
        <x-field label="Kata sandi baru" for="password" hint="Minimal 8 karakter.">
            <x-password-input name="password" autocomplete="new-password" required />
        </x-field>
        <x-field label="Ulangi kata sandi baru" for="password_confirmation">
            <x-password-input name="password_confirmation" autocomplete="new-password" required />
        </x-field>
        <x-button type="submit" class="w-full" processing="Menyimpan…">Simpan kata sandi</x-button>
    </form>

    <p class="mt-6 text-caption"><x-link :href="route('password.request')" muted class="font-normal">Minta tautan baru</x-link></p>
</x-layouts.auth>
