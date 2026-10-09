<?php

namespace Database\Factories;

use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Village>
 */
class VillageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Kampung Uji '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
