<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Repository>
 */
class RepositoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word();
        $owner = fake()->word();
        
        return [
            'external_id' => fake()->unique()->numberBetween(1000000, 9999999),
            'provider' => 'github',
            'full_name' => "{$owner}/{$name}",
            'name' => $name,
            'owner' => $owner,
            'default_branch' => 'main',
            'description' => fake()->paragraph(),
            'language' => fake()->randomElement(['PHP', 'Python', 'JavaScript', 'Go', 'Rust', 'Java']),
            'html_url' => "https://github.com/{$owner}/{$name}",
            'clone_url' => "https://github.com/{$owner}/{$name}.git",
            'stars_count' => fake()->numberBetween(0, 1000),
            'forks_count' => fake()->numberBetween(0, 100),
            'open_issues_count' => fake()->numberBetween(0, 50),
            'ci_enabled' => true,
            'is_active' => true,
        ];
    }
}
