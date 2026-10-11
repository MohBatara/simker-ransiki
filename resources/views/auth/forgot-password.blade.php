<x-layouts.auth title="Lupa kata sandi" :cover="$cover" :cover-credit="$coverCredit">
    <h1 class="text-page-title font-semibold text-ink">Lupa kata sandi</h1>
    <p class="mt-1 text-ink-soft">Masukkan email akun Anda. Kami kirim tautan untuk membuat kata sandi baru.</p>

    @if (session('status'))
        <x-alert tone="ok" class="mt-6">{{ session('status') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <x-field label="Email" for="email">
            <x-input name="email" type="email" autocomplete="email" autocapitalize="none" spellcheck="false" maxlength="255" required autofocus />
        </x-field>
        <x-button type="submit" class="w-full" processing="Mengirim…">Kirim tautan</x-button>
    </form>

    <p class="mt-6 text-caption text-ink-soft">Akun tanpa email? Hubungi Admin distrik untuk mengatur ulang kata sandi.</p>
    <p class="mt-2 text-caption"><x-link :href="route('login')" muted class="font-normal">Kembali ke halaman masuk</x-link></p>
</x-layouts.auth>
