<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Kelompok,
            'organization_id' => Organization::factory(),
            'is_active' => true,
            'must_change_password' => false,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => Role::Admin,
            'organization_id' => null,
        ]);
    }

    public function opd(?Opd $opd = null): static
    {
        return $this->state(fn () => [
            'role' => Role::Opd,
            'opd_id' => $opd?->id ?? Opd::factory(),
            'organization_id' => null,
        ]);
    }

    public function kelompok(Organization $organization): static
    {
        return $this->state(fn () => [
            'role' => Role::Kelompok,
            'organization_id' => $organization->id,
        ]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => ['must_change_password' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
