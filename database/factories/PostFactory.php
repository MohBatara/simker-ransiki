<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Berita Contoh '.fake()->unique()->numberBetween(1, 99999);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'category' => 'berita',
            'summary' => 'Ringkasan berita contoh.',
            'body' => "Isi berita contoh.\n\nParagraf kedua.",
            'status' => 'published',
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft', 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(['status' => 'published', 'published_at' => now()->addDays(3)]);
    }
}
