<?php

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Models\InstitutionType;
use App\Models\Organization;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Kelompok Uji '.fake()->unique()->numberBetween(1, 99999),
            'institution_type_id' => InstitutionType::factory(),
            'village_id' => Village::factory(),
            'status' => OrganizationStatus::Active,
            'reporting_starts_on' => '2026-07-01',
            'is_public' => false,
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => ['is_public' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => OrganizationStatus::Inactive]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => OrganizationStatus::Closed]);
    }
}
