<?php

namespace Database\Factories;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => 'NIB',
            'status' => LicenseStatus::None,
        ];
    }
}
