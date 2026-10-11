<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

/**
 * Membuat akun Administrator Distrik pertama di server (tidak ada daftar akun sendiri).
 * Kata sandi selalu ditanyakan tersembunyi, tidak lewat opsi, agar tidak tersimpan di riwayat shell.
 * Admin memilih kata sandinya sendiri, jadi tidak diwajibkan menggantinya saat masuk pertama.
 */
#[Signature('simker:create-admin {--name= : Nama lengkap} {--username= : Username untuk masuk} {--email= : Email (opsional)}')]
#[Description('Buat akun Administrator Distrik')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        try {
            return $this->createAdmin();
        } catch (InvalidArgumentException) {
            return self::FAILURE;
        }
    }

    private function createAdmin(): int
    {
        $name = $this->askValid('Nama lengkap', 'name', $this->option('name'), ['required', 'string', 'max:255']);

        $username = $this->askValid('Username (huruf kecil, angka, titik, minus, garis bawah)', 'username', $this->option('username'), [
            'required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username'),
        ], normalize: true);

        $email = $this->askValid('Email (kosongkan bila tidak ada)', 'email', $this->option('email'), [
            'nullable', 'email', 'max:255', Rule::unique('users', 'email'),
        ], normalize: true, optional: true);

        $password = $this->askPassword();

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'role' => Role::Admin,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->components->info("Akun Admin \"{$user->username}\" dibuat. Masuk di ".route('login'));

        return self::SUCCESS;
    }

    /**
     * Nilai dari opsi yang tidak sah langsung menggagalkan perintah (tidak interaktif);
     * jawaban yang tidak sah ditanyakan ulang.
     */
    private function askValid(string $question, string $field, ?string $given, array $rules, bool $normalize = false, bool $optional = false): ?string
    {
        while (true) {
            $value = $given ?? ($optional ? $this->ask($question, '') : $this->ask($question));
            $value = $normalize ? Str::lower(trim((string) $value)) : trim((string) $value);
            $value = $value === '' ? null : $value;

            $validator = Validator::make([$field => $value], [$field => $rules], [], $this->attributes());
            if ($validator->passes()) {
                return $value;
            }

            $this->components->error($validator->errors()->first($field));
            if ($given !== null) {
                throw new InvalidArgumentException;
            }
        }
    }

    private function askPassword(): string
    {
        while (true) {
            $password = (string) $this->secret('Kata sandi (minimal 8 karakter)');
            $confirmation = (string) $this->secret('Ulangi kata sandi');

            $validator = Validator::make(
                ['password' => $password, 'password_confirmation' => $confirmation],
                ['password' => ['required', 'confirmed', Password::min(8)]],
                [],
                $this->attributes(),
            );
            if ($validator->passes()) {
                return $password;
            }

            $this->components->error($validator->errors()->first('password'));
        }
    }

    private function attributes(): array
    {
        return ['name' => 'nama', 'username' => 'username', 'email' => 'email', 'password' => 'kata sandi'];
    }
}
