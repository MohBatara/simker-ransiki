<x-layouts.auth title="Buat kata sandi">
    <h1 class="text-page-title font-semibold text-ink">Buat kata sandi</h1>
    <p class="mt-1 text-ink-soft">Halo {{ $user->name }}. Buat kata sandi untuk akun <strong class="font-semibold text-ink">{{ $user->username }}</strong>.</p>

    @if ($current && ! $current->is($user))
        <x-alert tone="wait" class="mt-6">Browser ini sedang masuk sebagai {{ $current->name }}. Saat kata sandi disimpan, akun itu dikeluarkan.</x-alert>
    @endif

    <form novalidate method="POST" action="{{ request()->fullUrl() }}" class="mt-6 space-y-4">
        @csrf
        <x-form-errors />
        <x-field label="Kata sandi baru" for="password" hint="Minimal 8 karakter.">
            <x-password-input name="password" autocomplete="new-password" required autofocus />
        </x-field>
        <x-field label="Ulangi kata sandi baru" for="password_confirmation">
            <x-password-input name="password_confirmation" autocomplete="new-password" required />
        </x-field>
        <x-button type="submit" class="w-full" processing="Menyimpan…">Simpan dan masuk</x-button>
    </form>

    <p class="mt-6 text-caption text-ink-soft">Masuk berikutnya dengan username {{ $user->username }} atau email Anda.</p>
</x-layouts.auth>
