<form method="POST" action="{{ route('account.password.update') }}" class="space-y-4">
    @csrf
    @method('PUT')
    <x-field label="Kata sandi saat ini" for="current_password">
        <x-password-input name="current_password" autocomplete="current-password" required />
    </x-field>
    <x-field label="Kata sandi baru" for="password" hint="Minimal 8 karakter, berbeda dari kata sandi saat ini.">
        <x-password-input name="password" autocomplete="new-password" required />
    </x-field>
    <x-field label="Ulangi kata sandi baru" for="password_confirmation">
        <x-password-input name="password_confirmation" autocomplete="new-password" required />
    </x-field>
    <x-form-actions>
        <x-button type="submit" processing="Menyimpan…" @class(['w-full' => $fullWidth ?? false])>Simpan kata sandi</x-button>
    </x-form-actions>
</form>
