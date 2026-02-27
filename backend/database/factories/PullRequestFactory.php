<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PullRequest>
 */
class PullRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => \App\Models\Repository::factory(),
            'external_id' => $this->faker->unique()->randomNumber(9),
            'number' => $this->faker->randomNumber(4),
            'state' => $this->faker->randomElement(['open', 'closed', 'merged']),
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(),
            'head_branch' => 'feature/' . $this->faker->slug(),
            'base_branch' => 'main',
            'head_sha' => $this->faker->sha1(),
            'base_sha' => $this->faker->sha1(),
            'html_url' => $this->faker->url(),
            'diff_url' => $this->faker->url(),
            'additions' => $this->faker->randomNumber(3),
            'deletions' => $this->faker->randomNumber(3),
            'changed_files' => $this->faker->randomNumber(2),
            'commits_count' => $this->faker->randomNumber(2),
            'comments_count' => $this->faker->randomNumber(2),
            'review_status' => $this->faker->randomElement(['approved', 'changes_requested', 'pending', null]),
            'approvals_count' => $this->faker->randomNumber(1),
            'review_comments_count' => $this->faker->randomNumber(2),
            'ci_status' => $this->faker->randomElement(['success', 'failure', 'pending', 'error', null]),
            'ci_checks_count' => $this->faker->randomNumber(1),
            'ci_checks_passed' => $this->faker->randomNumber(1),
            'ci_checks_failed' => 0,
            'test_coverage' => $this->faker->randomFloat(2, 50, 100),
            'tests_total' => $this->faker->randomNumber(2),
            'tests_passed' => $this->faker->randomNumber(2),
            'tests_failed' => 0,
            'tests_skipped' => $this->faker->randomNumber(1),
            'cycle_time' => $this->faker->randomNumber(5),
            'time_to_first_review' => $this->faker->randomNumber(5),
            'time_to_approval' => $this->faker->randomNumber(5),
            'time_to_merge' => $this->faker->randomNumber(5),
            'labels' => json_encode(['bug', 'feature', 'documentation']),
            'is_draft' => false,
            'is_mergeable' => true,
            'is_hot' => false,
            'is_stale' => false,
            'assignees' => json_encode([]),
            'requested_reviewers' => json_encode([]),
            'metadata' => json_encode([]),
            'created_at' => $this->faker->dateTimeThisYear(),
            'updated_at' => $this->faker->dateTimeThisYear(),
        ];
    }
}
