<?php

namespace Database\Factories;

use App\Enums\HeroFocus;
use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HeroSlide> */
class HeroSlideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'photo_path' => 'site/hero-'.fake()->uuid().'.jpg',
            'caption' => 'Bidang usaha contoh',
            'alt_text' => 'Foto contoh kegiatan kelompok',
            'focus' => HeroFocus::Center,
            'position' => 1,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
