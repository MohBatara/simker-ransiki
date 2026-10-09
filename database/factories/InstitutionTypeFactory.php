<?php

namespace Database\Factories;

use App\Models\InstitutionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstitutionType>
 */
class InstitutionTypeFactory extends Factory
{
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => 'Jenis Uji '.$number,
            'code' => 'uji_'.$number,
        ];
    }
}
