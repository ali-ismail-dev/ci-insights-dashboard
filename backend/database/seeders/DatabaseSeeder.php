<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Database Seeder
 * 
 * Seeds the database with realistic test data for local development.
 * 
 * IMPORTANT: Only run in local/dev environments!
 * Production data should come from real GitHub webhooks.
 * 
 * @package Database\Seeders
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Safety check: prevent accidental production seeding
        if (app()->environment('production')) {
            $this->command->error('Cannot seed production database!');
            return;
        }
        
        $this->command->info('Seeding development database...');
        
        // Seed in dependency order
        $this->seedUsers();
        $this->seedRepositories();
        $this->seedPullRequests();
        $this->seedWebhookEvents();
        $this->seedTestRuns();
        $this->seedTestResults();
        $this->seedFileChanges();
        $this->seedAlertRules();
        $this->seedAlerts();
        $this->seedDailyMetrics();
        
        $this->command->info('✓ Database seeded successfully!');
    }
    
    /**
     * Seed users table
     */
    private function seedUsers(): void
    {
        $this->command->info('Seeding users...');
        
        $users = [
            [
                'external_id' => 12345678,
                'provider' => 'github',
                'username' => 'johndoe',
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'email_verified_at' => now(),
                'avatar_url' => 'https://avatars.githubusercontent.com/u/12345678',
                'bio' => 'Senior Software Engineer',
                'location' => 'San Francisco, CA',
                'company' => 'Tech Corp',
                'role' => 'admin',
                'is_active' => true,
                'last_login_at' => now()->subHours(2),
                'created_at' => now()->subMonths(6),
                'updated_at' => now(),
            ],
            [
                'external_id' => 23456789,
                'provider' => 'github',
                'username' => 'janedoe',
                'name' => 'Jane Doe',
                'email' => 'jane.doe@example.com',
                'email_verified_at' => now(),
                'avatar_url' => 'https://avatars.githubusercontent.com/u/23456789',
                'bio' => 'DevOps Engineer',
                'location' => 'New York, NY',
                'company' => 'Tech Corp',
                'role' => 'member',
                'is_active' => true,
                'last_login_at' => now()->subHours(5),
                'created_at' => now()->subMonths(3),
                'updated_at' => now(),
            ],
            [
                'external_id' => 34567890,
                'provider' => 'github',
                'username' => 'bobsmith',
                'name' => 'Bob Smith',
                'email' => 'bob.smith@example.com',
                'email_verified_at' => now(),
                'avatar_url' => 'https://avatars.githubusercontent.com/u/34567890',
                'bio' => 'Backend Developer',
                'location' => 'Austin, TX',
                'company' => 'Tech Corp',
                'role' => 'member',
                'is_active' => true,
                'last_login_at' => now()->subDay(),
                'created_at' => now()->subMonths(4),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('users')->insert($users);
        $this->command->info('✓ Seeded ' . count($users) . ' users');
    }
    
    /**
     * Seed repositories table
     */
    private function seedRepositories(): void
    {
        $this->command->info('Seeding repositories...');
        
        $repositories = [
            [
                'external_id' => 987654321,
                'provider' => 'github',
                'full_name' => 'techcorp/backend-api',
                'name' => 'backend-api',
                'owner' => 'techcorp',
                'default_branch' => 'main',
                'description' => 'Backend API service for CI Insights Dashboard',
                'language' => 'PHP',
                'html_url' => 'https://github.com/techcorp/backend-api',
                'clone_url' => 'https://github.com/techcorp/backend-api.git',
                'stars_count' => 125,
                'forks_count' => 32,
                'open_issues_count' => 8,
                'webhook_secret' => Hash::make('secret123'),
                'webhook_verified_at' => now(),
                'ci_enabled' => true,
                'is_active' => true,
                'is_private' => false,
                'last_synced_at' => now(),
                'created_at' => now()->subMonths(12),
                'updated_at' => now(),
            ],
            [
                'external_id' => 987654322,
                'provider' => 'github',
                'full_name' => 'techcorp/frontend-app',
                'name' => 'frontend-app',
                'owner' => 'techcorp',
                'default_branch' => 'main',
                'description' => 'Frontend React application',
                'language' => 'TypeScript',
                'html_url' => 'https://github.com/techcorp/frontend-app',
                'clone_url' => 'https://github.com/techcorp/frontend-app.git',
                'stars_count' => 98,
                'forks_count' => 15,
                'open_issues_count' => 5,
                'webhook_secret' => Hash::make('secret456'),
                'webhook_verified_at' => now(),
                'ci_enabled' => true,
                'is_active' => true,
                'is_private' => false,
                'last_synced_at' => now(),
                'created_at' => now()->subMonths(10),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('repositories')->insert($repositories);
        $this->command->info('✓ Seeded ' . count($repositories) . ' repositories');
    }
    
    /**
     * Seed pull requests table
     */
    private function seedPullRequests(): void
    {
        $this->command->info('Seeding pull requests...');
        
        $pullRequests = [];
        $states = ['open', 'merged', 'closed'];
        $ciStatuses = ['success', 'failure', 'pending'];
        
        // Generate 50 PRs
        for ($i = 1; $i <= 50; $i++) {
            $state = $states[array_rand($states)];
            $ciStatus = $ciStatuses[array_rand($ciStatuses)];
            $createdAt = now()->subDays(rand(1, 60));
            $mergedAt = $state === 'merged' ? $createdAt->copy()->addHours(rand(2, 72)) : null;
            
            $pullRequests[] = [
                'repository_id' => rand(1, 2),
                'author_id' => rand(1, 3),
                'external_id' => 1000000 + $i,
                'number' => $i,
                'state' => $state,
                'title' => 'Feature: ' . $this->generateFeatureTitle(),
                'description' => 'This PR implements ' . Str::random(20),
                'head_branch' => 'feature/branch-' . $i,
                'base_branch' => 'main',
                'head_sha' => Str::random(40),
                'base_sha' => Str::random(40),
                'html_url' => "https://github.com/techcorp/backend-api/pull/{$i}",
                'additions' => rand(50, 500),
                'deletions' => rand(20, 200),
                'changed_files' => rand(3, 15),
                'commits_count' => rand(1, 10),
                'comments_count' => rand(0, 20),
                'review_status' => $state === 'merged' ? 'approved' : null,
                'approvals_count' => $state === 'merged' ? rand(1, 3) : 0,
                'ci_status' => $ciStatus,
                'ci_checks_count' => rand(3, 8),
                'ci_checks_passed' => $ciStatus === 'success' ? rand(3, 8) : rand(0, 5),
                'ci_checks_failed' => $ciStatus === 'failure' ? rand(1, 3) : 0,
                'test_coverage' => rand(70, 95) + (rand(0, 99) / 100),
                'tests_total' => rand(50, 200),
                'tests_passed' => rand(45, 195),
                'tests_failed' => rand(0, 5),
                'cycle_time' => $mergedAt ? $createdAt->diffInSeconds($mergedAt) : null,
                'time_to_first_review' => rand(1800, 86400),
                'is_stale' => $state === 'open' && $createdAt->diffInDays(now()) > 14,
                'created_at' => $createdAt,
                'updated_at' => now(),
                'merged_at' => $mergedAt,
            ];
        }
        
        DB::table('pull_requests')->insert($pullRequests);
        $this->command->info('✓ Seeded ' . count($pullRequests) . ' pull requests');
    }
    
    /**
     * Generate random feature title
     */
    private function generateFeatureTitle(): string
    {
        $features = [
            'Add user authentication',
            'Implement webhook processing',
            'Add test coverage tracking',
            'Fix flaky test detection',
            'Update CI pipeline',
            'Refactor database queries',
            'Add error handling',
            'Implement caching layer',
            'Add API documentation',
            'Fix security vulnerability',
        ];
        
        return $features[array_rand($features)];
    }
    
    /**
     * Seed remaining tables (simplified for brevity)
     */
    private function seedWebhookEvents(): void
    {
        $this->command->info('✓ Skipping webhook_events (populated by real webhooks)');
    }
    
    private function seedTestRuns(): void
    {
        $this->command->info('✓ Skipping test_runs (will be populated by CI integration)');
    }
    
    private function seedTestResults(): void
    {
        $this->command->info('✓ Skipping test_results (will be populated by CI integration)');
    }
    
    private function seedFileChanges(): void
    {
        $this->command->info('✓ Skipping file_changes (will be populated by PR analysis)');
    }
    
    private function seedAlertRules(): void
    {
        $this->command->info('Seeding alert rules...');
        
        $rules = [
            [
                'repository_id' => 1,
                'created_by_user_id' => 1,
                'name' => 'Flaky Test Detection',
                'description' => 'Alert when test flakiness score > 30',
                'rule_type' => 'flaky_test',
                'conditions' => json_encode([
                    'metric' => 'flakiness_score',
                    'operator' => 'greater_than',
                    'threshold' => 30,
                    'timeframe' => '7_days',
                ]),
                'severity' => 'high',
                'priority' => 8,
                'notification_channels' => json_encode(['email', 'slack']),
                'cooldown_minutes' => 60,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('alert_rules')->insert($rules);
        $this->command->info('✓ Seeded ' . count($rules) . ' alert rules');
    }
    
    private function seedAlerts(): void
    {
        $this->command->info('✓ Skipping alerts (will be triggered by alert rules)');
    }
    
    private function seedDailyMetrics(): void
    {
        $this->command->info('✓ Skipping daily_metrics (calculated by scheduled job)');
    }
}