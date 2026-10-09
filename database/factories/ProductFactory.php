<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Produk Uji '.fake()->unique()->numberBetween(1, 99999),
            'unit' => 'kg',
        ];
    }
}
