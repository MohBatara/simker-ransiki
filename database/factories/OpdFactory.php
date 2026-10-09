<?php

namespace Database\Factories;

use App\Models\Opd;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opd>
 */
class OpdFactory extends Factory
{
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => 'Dinas Uji '.$number,
            'short_name' => 'UJI'.$number,
            'description' => null,
        ];
    }
}
