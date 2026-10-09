<?php

namespace Database\Factories;

use App\Models\BusinessSector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessSector>
 */
class BusinessSectorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Bidang Uji '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
