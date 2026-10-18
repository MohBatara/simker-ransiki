<x-field label="Kata sandi saat ini" for="current_password" required>
    <x-password-input name="current_password" autocomplete="current-password" required />
</x-field>
<x-field label="Kata sandi baru" for="password" hint="Minimal 8 karakter, berbeda dari kata sandi saat ini." required>
    <x-password-input name="password" autocomplete="new-password" required />
</x-field>
<x-field label="Ulangi kata sandi baru" for="password_confirmation" required>
    <x-password-input name="password_confirmation" autocomplete="new-password" required />
</x-field>
