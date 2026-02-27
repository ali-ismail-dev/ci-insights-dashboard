<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TestRun>
 */
class TestRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ci_provider' => 'github_actions',
            'external_id' => fake()->unique()->numberBetween(1000000, 9999999999),
            'workflow_name' => fake()->words(2, true),
            'job_name' => fake()->words(2, true),
            'branch' => fake()->randomElement(['main', 'develop', 'feature-branch']),
            'commit_sha' => fake()->sha1(),
            'status' => fake()->randomElement(['success', 'failure', 'error', 'canceled']),
            'total_tests' => fake()->numberBetween(50, 200),
            'passed_tests' => fn(array $attrs) => (int)($attrs['total_tests'] * fake()->numberBetween(80, 99) / 100),
            'failed_tests' => fn(array $attrs) => $attrs['total_tests'] - $attrs['passed_tests'],
            'skipped_tests' => fake()->numberBetween(0, 10),
            'flaky_tests' => fake()->numberBetween(0, 5),
            'line_coverage' => fake()->numberBetween(50, 100),
            'branch_coverage' => fake()->numberBetween(40, 100),
            'method_coverage' => fake()->numberBetween(50, 100),
            'duration' => fake()->numberBetween(60, 600),
            'started_at' => now()->subHours(1),
            'completed_at' => now(),
            'is_retry' => false,
            'retry_attempt' => 0,
        ];
    }
}
