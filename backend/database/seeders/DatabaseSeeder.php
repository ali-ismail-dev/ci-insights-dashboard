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
 * Fills all columns so dashboards and graphs display correctly.
 *
 * IMPORTANT: Only run in local/dev environments!
 * Production data should come from real GitHub webhooks.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('Cannot seed production database!');
            return;
        }

        $this->command->info('Seeding development database...');

        $this->seedUsers();
        $this->seedRepositories();
        $this->seedPullRequests();
        $this->seedAlertRules();
        $this->seedTestRuns();
        $this->seedTestResults();
        $this->seedDailyMetrics();
        $this->seedAlerts();
        $this->seedFileChanges();

        $this->seedWebhookEvents(); // no-op, kept for order

        $this->command->info('✓ Database seeded successfully!');
    }

    private function seedUsers(): void
    {
        $this->command->info('Seeding users...');

        $now = now();
        $users = [
            [
                'external_id' => 12345678,
                'provider' => 'github',
                'username' => 'johndoe',
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'email_verified_at' => $now,
                'avatar_url' => 'https://avatars.githubusercontent.com/u/12345678',
                'bio' => 'Senior Software Engineer',
                'location' => 'San Francisco, CA',
                'company' => 'Tech Corp',
                'website_url' => 'https://johndoe.dev',
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
                'role' => 'admin',
                'permissions' => json_encode(['can_view_private_repos', 'can_manage_alerts']),
                'preferences' => json_encode(['theme' => 'light', 'dashboard_layout' => 'default']),
                'timezone' => 'America/Los_Angeles',
                'email_notifications' => true,
                'slack_notifications' => false,
                'last_login_at' => $now->copy()->subHours(2),
                'last_login_ip' => '192.168.1.1',
                'last_activity_at' => $now->copy()->subMinutes(30),
                'is_active' => true,
                'created_at' => $now->copy()->subMonths(6),
                'updated_at' => $now,
            ],
            [
                'external_id' => 23456789,
                'provider' => 'github',
                'username' => 'janedoe',
                'name' => 'Jane Doe',
                'email' => 'jane.doe@example.com',
                'email_verified_at' => $now,
                'avatar_url' => 'https://avatars.githubusercontent.com/u/23456789',
                'bio' => 'DevOps Engineer',
                'location' => 'New York, NY',
                'company' => 'Tech Corp',
                'website_url' => null,
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
                'role' => 'member',
                'permissions' => null,
                'preferences' => null,
                'timezone' => 'America/New_York',
                'email_notifications' => true,
                'slack_notifications' => true,
                'last_login_at' => $now->copy()->subHours(5),
                'last_login_ip' => '192.168.1.2',
                'last_activity_at' => $now->copy()->subHours(1),
                'is_active' => true,
                'created_at' => $now->copy()->subMonths(3),
                'updated_at' => $now,
            ],
            [
                'external_id' => 34567890,
                'provider' => 'github',
                'username' => 'bobsmith',
                'name' => 'Bob Smith',
                'email' => 'bob.smith@example.com',
                'email_verified_at' => $now,
                'avatar_url' => 'https://avatars.githubusercontent.com/u/34567890',
                'bio' => 'Backend Developer',
                'location' => 'Austin, TX',
                'company' => 'Tech Corp',
                'website_url' => null,
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
                'role' => 'member',
                'permissions' => null,
                'preferences' => null,
                'timezone' => 'UTC',
                'email_notifications' => false,
                'slack_notifications' => false,
                'last_login_at' => $now->copy()->subDay(),
                'last_login_ip' => null,
                'last_activity_at' => $now->copy()->subDays(2),
                'is_active' => true,
                'created_at' => $now->copy()->subMonths(4),
                'updated_at' => $now,
            ],
        ];

        DB::table('users')->insert($users);
        $this->command->info('✓ Seeded ' . count($users) . ' users');
    }

    private function seedRepositories(): void
    {
        $this->command->info('Seeding repositories...');

        $now = now();
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
                'webhook_verified_at' => $now,
                'ci_config' => json_encode(['provider' => 'github_actions', 'workflow' => 'ci.yml']),
                'ci_enabled' => true,
                'is_active' => true,
                'is_private' => false,
                'metadata' => json_encode(['topics' => ['api', 'laravel'], 'license' => 'MIT']),
                'last_synced_at' => $now,
                'created_at' => $now->copy()->subMonths(12),
                'updated_at' => $now,
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
                'webhook_verified_at' => $now,
                'ci_config' => json_encode(['provider' => 'github_actions', 'workflow' => 'build.yml']),
                'ci_enabled' => true,
                'is_active' => true,
                'is_private' => false,
                'metadata' => json_encode(['topics' => ['react', 'typescript']]),
                'last_synced_at' => $now,
                'created_at' => $now->copy()->subMonths(10),
                'updated_at' => $now,
            ],
        ];

        DB::table('repositories')->insert($repositories);
        $this->command->info('✓ Seeded ' . count($repositories) . ' repositories');
    }

    private function seedPullRequests(): void
    {
        $this->command->info('Seeding pull requests...');

        $features = [
            'Add user authentication', 'Implement webhook processing', 'Add test coverage tracking',
            'Fix flaky test detection', 'Update CI pipeline', 'Refactor database queries',
            'Add error handling', 'Implement caching layer', 'Add API documentation', 'Fix security vulnerability',
        ];
        $states = ['open', 'merged', 'closed'];
        $ciStatuses = ['success', 'failure', 'pending', 'error'];

        $pullRequests = [];
        for ($i = 1; $i <= 50; $i++) {
            $state = $states[array_rand($states)];
            $ciStatus = $ciStatuses[array_rand($ciStatuses)];
            $createdAt = now()->subDays(rand(1, 60));
            $mergedAt = $state === 'merged' ? $createdAt->copy()->addHours(rand(2, 72)) : null;
            $closedAt = in_array($state, ['merged', 'closed']) ? ($mergedAt ?? $createdAt->copy()->addDays(rand(1, 5))) : null;
            $firstReviewAt = $createdAt->copy()->addHours(rand(1, 24));
            $approvedAt = $state === 'merged' ? $firstReviewAt->copy()->addHours(rand(2, 48)) : null;

            $testsTotal = rand(50, 200);
            $testsPassed = $ciStatus === 'success' ? $testsTotal - rand(0, 5) : rand(0, (int)($testsTotal * 0.9));
            $testsFailed = min($testsTotal - $testsPassed, rand(0, 15));
            $testsSkipped = $testsTotal - $testsPassed - $testsFailed;

            // All time deltas must be non-negative (unsignedInteger in DB)
            $cycleTime = $mergedAt ? abs($createdAt->diffInSeconds($mergedAt, false)) : null;
            $timeToFirstReview = abs($firstReviewAt->diffInSeconds($createdAt, false));
            $timeToApproval = $approvedAt ? abs($approvedAt->diffInSeconds($createdAt, false)) : null;
            $timeToMerge = ($approvedAt && $mergedAt) ? abs($mergedAt->diffInSeconds($approvedAt, false)) : null;

            $pullRequests[] = [
                'repository_id' => rand(1, 2),
                'author_id' => rand(1, 3),
                'external_id' => 1000000 + $i,
                'number' => $i,
                'state' => $state,
                'title' => 'Feature: ' . $features[array_rand($features)],
                'description' => 'This PR implements ' . Str::random(30) . '. Closes #' . rand(1, 20),
                'head_branch' => 'feature/branch-' . $i,
                'base_branch' => 'main',
                'head_sha' => Str::random(40),
                'base_sha' => Str::random(40),
                'html_url' => 'https://github.com/techcorp/backend-api/pull/' . $i,
                'diff_url' => 'https://github.com/techcorp/backend-api/pull/' . $i . '.diff',
                'additions' => rand(50, 500),
                'deletions' => rand(20, 200),
                'changed_files' => rand(3, 15),
                'commits_count' => rand(1, 10),
                'comments_count' => rand(0, 20),
                'review_status' => $state === 'merged' ? 'approved' : ($state === 'open' ? 'pending' : 'changes_requested'),
                'approvals_count' => $state === 'merged' ? rand(1, 3) : 0,
                'review_comments_count' => rand(0, 15),
                'ci_status' => $ciStatus,
                'ci_checks_count' => rand(3, 8),
                'ci_checks_passed' => $ciStatus === 'success' ? rand(3, 8) : rand(0, 5),
                'ci_checks_failed' => $ciStatus === 'failure' ? rand(1, 3) : 0,
                'test_coverage' => round(rand(7000, 9500) / 100, 2),
                'tests_total' => $testsTotal,
                'tests_passed' => $testsPassed,
                'tests_failed' => $testsFailed,
                'tests_skipped' => $testsSkipped,
                'cycle_time' => $cycleTime,
                'time_to_first_review' => $timeToFirstReview,
                'time_to_approval' => $timeToApproval,
                'time_to_merge' => $timeToMerge,
                'labels' => json_encode(array_slice(['bug', 'feature', 'documentation', 'refactor', 'tests'], 0, rand(0, 3))),
                'assignees' => json_encode(rand(0, 1) ? [rand(1, 3)] : []),
                'requested_reviewers' => json_encode([2, 3]),
                'metadata' => json_encode(['milestone' => 'v1.' . rand(0, 5)]),
                'is_draft' => $state === 'open' && (rand(0, 10) > 7),
                'is_mergeable' => $state === 'open' ? (bool)rand(0, 1) : true,
                'is_hot' => rand(0, 10) > 8,
                'is_stale' => $state === 'open' && $createdAt->diffInDays(now()) > 14,
                'first_commit_at' => $createdAt,
                'first_review_at' => $firstReviewAt,
                'approved_at' => $approvedAt,
                'merged_at' => $mergedAt,
                'closed_at' => $closedAt,
                'last_activity_at' => $createdAt->copy()->addHours(rand(0, 100)),
                'created_at' => $createdAt,
                'updated_at' => now(),
            ];
        }

        DB::table('pull_requests')->insert($pullRequests);
        $this->command->info('✓ Seeded ' . count($pullRequests) . ' pull requests');
    }

    private function seedAlertRules(): void
    {
        $this->command->info('Seeding alert rules...');

        $now = now();
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
                    'consecutive_violations' => 3,
                ]),
                'severity' => 'high',
                'priority' => 8,
                'notification_channels' => json_encode(['email', 'slack', 'database']),
                'notification_recipients' => json_encode([1, 2]),
                'cooldown_minutes' => 60,
                'max_alerts_per_day' => 10,
                'message_template' => 'Flaky test **{{test_name}}** detected (score: {{flakiness_score}}).',
                'schedule' => json_encode(['interval' => 'hourly']),
                'is_active' => true,
                'last_evaluated_at' => $now->copy()->subMinutes(15),
                'last_triggered_at' => $now->copy()->subHours(2),
                'trigger_count' => 5,
                'metadata' => json_encode(['team' => 'backend']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'repository_id' => null,
                'created_by_user_id' => 1,
                'name' => 'Stale PR',
                'description' => 'Alert when PR has no activity for 14 days',
                'rule_type' => 'stale_pr',
                'conditions' => json_encode([
                    'metric' => 'days_since_activity',
                    'operator' => 'greater_than',
                    'threshold' => 14,
                ]),
                'severity' => 'medium',
                'priority' => 5,
                'notification_channels' => json_encode(['email', 'database']),
                'notification_recipients' => null,
                'cooldown_minutes' => 1440,
                'max_alerts_per_day' => 5,
                'message_template' => 'PR #{{pr_number}} has been stale for {{days}} days.',
                'schedule' => json_encode(['cron' => '0 9 * * *']),
                'is_active' => true,
                'last_evaluated_at' => $now->copy()->subDay(),
                'last_triggered_at' => null,
                'trigger_count' => 0,
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'repository_id' => 2,
                'created_by_user_id' => 2,
                'name' => 'Coverage Drop',
                'description' => 'Alert when coverage drops more than 5%',
                'rule_type' => 'coverage_drop',
                'conditions' => json_encode([
                    'metric' => 'line_coverage',
                    'operator' => 'decreased_by',
                    'threshold' => 5,
                    'timeframe' => '7_days',
                ]),
                'severity' => 'medium',
                'priority' => 6,
                'notification_channels' => json_encode(['database']),
                'notification_recipients' => json_encode([2]),
                'cooldown_minutes' => 120,
                'max_alerts_per_day' => 3,
                'message_template' => null,
                'schedule' => null,
                'is_active' => true,
                'last_evaluated_at' => null,
                'last_triggered_at' => null,
                'trigger_count' => 0,
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('alert_rules')->insert($rules);
        $this->command->info('✓ Seeded ' . count($rules) . ' alert rules');
    }

    private function seedTestRuns(): void
    {
        $this->command->info('Seeding test runs...');

        $statuses = ['success', 'failure', 'error', 'canceled'];
        $branches = ['main', 'develop', 'feature/auth', 'feature/api'];
        $now = now();
        $runs = [];

        for ($i = 1; $i <= 60; $i++) {
            $repoId = rand(1, 2);
            $prId = rand(0, 10) > 3 ? rand(1, 50) : null;
            $status = $statuses[array_rand($statuses)];
            $startedAt = $now->copy()->subDays(rand(0, 30))->subMinutes(rand(0, 1440));
            $duration = rand(120, 900);
            $completedAt = $startedAt->copy()->addSeconds($duration);

            $totalTests = rand(80, 250);
            $passed = $status === 'success' ? $totalTests - rand(0, 5) : rand(0, (int)($totalTests * 0.8));
            $remaining = $totalTests - $passed;
            $failed = min($remaining, rand(0, max(0, (int)($remaining * 0.8))));
            $skipped = $remaining - $failed;
            $flaky = rand(0, min(5, $passed));

            $lineCov = $status === 'success' ? round(rand(7000, 9500) / 100, 2) : round(rand(5000, 8500) / 100, 2);
            $branchCov = round($lineCov * (rand(80, 100) / 100), 2);
            $methodCov = round($lineCov * (rand(85, 100) / 100), 2);

            $runs[] = [
                'repository_id' => $repoId,
                'pull_request_id' => $prId,
                'ci_provider' => 'github_actions',
                'external_id' => 'run-' . (10000 + $i),
                'workflow_name' => 'CI',
                'job_name' => 'test',
                'branch' => $branches[array_rand($branches)],
                'commit_sha' => Str::random(40),
                'status' => $status,
                'total_tests' => $totalTests,
                'passed_tests' => $passed,
                'failed_tests' => $failed,
                'skipped_tests' => $skipped,
                'flaky_tests' => $flaky,
                'line_coverage' => $lineCov,
                'branch_coverage' => $branchCov,
                'method_coverage' => $methodCov,
                'duration' => $duration,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'failed_tests_details' => $failed > 0 ? json_encode([['name' => 'ExampleTest::testOne', 'error' => 'Assertion failed']]) : null,
                'flaky_tests_details' => $flaky > 0 ? json_encode([['name' => 'FlakyTest::testTwo', 'retries' => 2]]) : null,
                'coverage_report' => json_encode(['summary' => ['lines' => $lineCov, 'branches' => $branchCov]]),
                'run_url' => 'https://github.com/techcorp/backend-api/actions/runs/' . (100000 + $i),
                'logs_url' => 'https://github.com/techcorp/backend-api/actions/runs/' . (100000 + $i) . '/logs',
                'environment' => json_encode(['os' => 'ubuntu-latest', 'php' => '8.2']),
                'is_retry' => (bool)rand(0, 5),
                'retry_attempt' => rand(0, 2),
                'created_at' => $startedAt,
                'updated_at' => $completedAt,
            ];
        }

        DB::table('test_runs')->insert($runs);
        $this->command->info('✓ Seeded ' . count($runs) . ' test runs');
    }

    private function seedTestResults(): void
    {
        $this->command->info('Seeding test results...');

        $testRunIds = DB::table('test_runs')->pluck('id')->toArray();
        $statuses = ['passed', 'failed', 'skipped', 'error'];
        $classes = ['UserTest', 'AuthTest', 'ApiTest', 'RepositoryTest', 'FlakyTest'];
        $methods = ['testLogin', 'testCreate', 'testUpdate', 'testDelete', 'testValidation'];
        $files = ['tests/Unit/UserTest.php', 'tests/Feature/AuthTest.php', 'tests/Unit/ApiTest.php'];
        $results = [];

        foreach (array_slice($testRunIds, 0, 25) as $testRunId) {
            $run = DB::table('test_runs')->where('id', $testRunId)->first();
            $repoId = $run->repository_id;
            $executedAt = $run->started_at ?? now();

            for ($t = 0; $t < rand(15, 40); $t++) {
                $class = $classes[array_rand($classes)];
                $method = $methods[array_rand($methods)];
                $file = $files[array_rand($files)];
                $status = $statuses[array_rand($statuses)];
                $identifier = $file . '::' . $class . '::' . $method . '-' . $t;
                $duration = rand(10, 500);
                $isFlaky = (bool)rand(0, 8);
                $passedOnRetry = $isFlaky && $status === 'passed';
                $flakinessScore = $isFlaky ? round(rand(2500, 8000) / 100, 2) : (rand(0, 5) ? rand(0, 15) : null);
                $failureRate = $status === 'failed' ? rand(10, 100) : ($isFlaky ? rand(20, 80) : null);

                $results[] = [
                    'test_run_id' => $testRunId,
                    'repository_id' => $repoId,
                    'test_identifier' => $identifier,
                    'test_name' => $class . '::' . $method,
                    'test_file' => $file,
                    'test_class' => $class,
                    'test_method' => $method,
                    'status' => $status,
                    'duration' => $duration,
                    'error_message' => $status === 'failed' ? 'Assertion failed: expected true' : null,
                    'stack_trace' => $status === 'failed' ? ' at UserTest.php:42' : null,
                    'failure_type' => $status === 'failed' ? 'assertion' : null,
                    'is_flaky' => $isFlaky,
                    'passed_on_retry' => $passedOnRetry,
                    'retry_count' => $passedOnRetry ? 1 : 0,
                    'flakiness_score' => $flakinessScore,
                    'failure_rate' => $failureRate,
                    'assertions_count' => rand(1, 20),
                    'tags' => json_encode(['unit', 'feature']),
                    'metadata' => json_encode([]),
                    'executed_at' => $executedAt,
                    'created_at' => $executedAt,
                    'updated_at' => $executedAt,
                ];
            }
        }

        foreach (array_chunk($results, 100) as $chunk) {
            DB::table('test_results')->insert($chunk);
        }
        $this->command->info('✓ Seeded ' . count($results) . ' test results');
    }

    private function seedDailyMetrics(): void
    {
        $this->command->info('Seeding daily metrics...');

        $repos = [1, 2];
        $rows = [];
        $now = now();

        foreach ($repos as $repoId) {
            for ($d = 45; $d >= 0; $d--) {
                $date = $now->copy()->subDays($d);
                $metricDate = $date->format('Y-m-d');
                $prsOpened = rand(0, 5);
                $prsMerged = rand(0, 4);
                $prsClosed = rand(0, 2);
                $prsActive = rand(5, 25);
                $runsTotal = rand(10, 50);
                $runsPassed = (int)($runsTotal * (rand(70, 98) / 100));
                $runsFailed = $runsTotal - $runsPassed;
                $successRate = round(($runsPassed / $runsTotal) * 100, 2);
                $avgDuration = round(rand(300, 900) / 60, 2);
                $avgLine = round(rand(7200, 9200) / 100, 2);
                $avgBranch = round($avgLine * (rand(85, 98) / 100), 2);
                $trend = rand(-20, 20) / 10;
                $flakyDetected = rand(0, 3);
                $flakyFixed = rand(0, 2);
                $avgFlakiness = $flakyDetected > 0 ? round(rand(1500, 4500) / 100, 2) : null;
                $contributors = rand(2, 8);
                $commits = rand(10, 80);
                $codeChanges = rand(500, 3000);
                $alertsTriggered = rand(0, 2);
                $alertsResolved = rand(0, 2);
                $avgCycle = round(rand(400, 2400) / 100, 2);
                $medianCycle = round($avgCycle * (rand(80, 120) / 100), 2);
                $avgTtfReview = round(rand(200, 1200) / 100, 2);
                $avgTtm = round(rand(100, 600) / 100, 2);
                $isFinal = $d > 0;

                $rows[] = [
                    'repository_id' => $repoId,
                    'metric_date' => $metricDate,
                    'prs_opened' => $prsOpened,
                    'prs_merged' => $prsMerged,
                    'prs_closed' => $prsClosed,
                    'prs_active' => $prsActive,
                    'avg_cycle_time' => $avgCycle,
                    'median_cycle_time' => $medianCycle,
                    'avg_time_to_first_review' => $avgTtfReview,
                    'avg_time_to_merge' => $avgTtm,
                    'test_runs_total' => $runsTotal,
                    'test_runs_passed' => $runsPassed,
                    'test_runs_failed' => $runsFailed,
                    'ci_success_rate' => $successRate,
                    'avg_test_duration' => $avgDuration,
                    'avg_line_coverage' => $avgLine,
                    'avg_branch_coverage' => $avgBranch,
                    'coverage_trend' => $trend,
                    'flaky_tests_detected' => $flakyDetected,
                    'flaky_tests_fixed' => $flakyFixed,
                    'avg_flakiness_score' => $avgFlakiness,
                    'active_contributors' => $contributors,
                    'total_commits' => $commits,
                    'total_code_changes' => $codeChanges,
                    'alerts_triggered' => $alertsTriggered,
                    'alerts_resolved' => $alertsResolved,
                    'metadata' => json_encode(['source' => 'seeder']),
                    'calculated_at' => $date->copy()->endOfDay(),
                    'is_final' => $isFinal,
                    'created_at' => $date,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('daily_metrics')->insert($chunk);
        }
        $this->command->info('✓ Seeded ' . count($rows) . ' daily metrics');
    }

    private function seedAlerts(): void
    {
        $this->command->info('Seeding alerts...');

        $now = now();
        $alerts = [
            [
                'alert_rule_id' => 1,
                'repository_id' => 1,
                'pull_request_id' => 1,
                'alert_type' => 'flaky_test',
                'severity' => 'high',
                'title' => 'Flaky test detected in CI',
                'message' => 'Test `UserTest::testLogin` is flaky across recent runs (score: 45).',
                'context' => json_encode(['test_name' => 'UserTest::testLogin', 'flakiness_score' => 45, 'threshold' => 30, 'run_count' => 11]),
                'status' => 'open',
                'acknowledged_at' => null,
                'acknowledged_by_user_id' => null,
                'resolved_at' => null,
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
                'notification_channels' => json_encode(['email', 'database']),
                'notification_status' => json_encode(['email' => 'sent', 'database' => 'sent']),
                'notified_at' => $now->copy()->subHour(),
                'fingerprint' => hash('sha256', 'flaky_test_UserTest::testLogin_1'),
                'parent_alert_id' => null,
                'occurrence_count' => 1,
                'metadata' => null,
                'created_at' => $now->copy()->subHour(),
                'updated_at' => $now,
            ],
            [
                'alert_rule_id' => 1,
                'repository_id' => 2,
                'pull_request_id' => 10,
                'alert_type' => 'ci_failure',
                'severity' => 'critical',
                'title' => 'CI pipeline failing on main',
                'message' => 'Frontend pipeline has failed on branch main for the last 3 runs.',
                'context' => json_encode(['branch' => 'main', 'failure_count' => 3]),
                'status' => 'open',
                'acknowledged_at' => null,
                'acknowledged_by_user_id' => null,
                'resolved_at' => null,
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
                'notification_channels' => json_encode(['slack', 'database']),
                'notification_status' => json_encode(['slack' => 'sent', 'database' => 'sent']),
                'notified_at' => $now->copy()->subMinutes(30),
                'fingerprint' => hash('sha256', 'ci_failure_main_2'),
                'parent_alert_id' => null,
                'occurrence_count' => 3,
                'metadata' => null,
                'created_at' => $now->copy()->subMinutes(30),
                'updated_at' => $now,
            ],
            [
                'alert_rule_id' => 2,
                'repository_id' => 1,
                'pull_request_id' => 5,
                'alert_type' => 'stale_pr',
                'severity' => 'medium',
                'title' => 'Stale pull request',
                'message' => 'PR #5 has had no activity for 16 days.',
                'context' => json_encode(['pr_number' => 5, 'days' => 16]),
                'status' => 'acknowledged',
                'acknowledged_at' => $now->copy()->subDay(),
                'acknowledged_by_user_id' => 1,
                'resolved_at' => null,
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
                'notification_channels' => json_encode(['database']),
                'notification_status' => json_encode(['database' => 'sent']),
                'notified_at' => $now->copy()->subDays(2),
                'fingerprint' => hash('sha256', 'stale_pr_5'),
                'parent_alert_id' => null,
                'occurrence_count' => 1,
                'metadata' => null,
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now,
            ],
            [
                'alert_rule_id' => 1,
                'repository_id' => 1,
                'pull_request_id' => 3,
                'alert_type' => 'flaky_test',
                'severity' => 'high',
                'title' => 'Flaky test resolved',
                'message' => 'Test `ApiTest::testCreate` was flaky; fixed in latest run.',
                'context' => json_encode(['test_name' => 'ApiTest::testCreate']),
                'status' => 'resolved',
                'acknowledged_at' => $now->copy()->subDays(3),
                'acknowledged_by_user_id' => 2,
                'resolved_at' => $now->copy()->subDays(2),
                'resolved_by_user_id' => 2,
                'resolution_notes' => 'Fixed by stabilizing test order.',
                'notification_channels' => json_encode(['email', 'database']),
                'notification_status' => json_encode(['email' => 'sent', 'database' => 'sent']),
                'notified_at' => $now->copy()->subDays(3),
                'fingerprint' => hash('sha256', 'flaky_ApiTest_3'),
                'parent_alert_id' => null,
                'occurrence_count' => 1,
                'metadata' => null,
                'created_at' => $now->copy()->subDays(3),
                'updated_at' => $now,
            ],
        ];

        DB::table('alerts')->insert($alerts);
        $this->command->info('✓ Seeded ' . count($alerts) . ' alerts');
    }

    private function seedFileChanges(): void
    {
        $this->command->info('Seeding file changes...');

        $extensions = ['php', 'ts', 'tsx', 'vue', 'css'];
        $dirs = ['app/Http', 'app/Models', 'resources/js', 'tests', 'database'];
        $changeTypes = ['added', 'modified', 'modified', 'modified', 'deleted', 'renamed'];
        $rows = [];
        $prIdsByRepo = DB::table('pull_requests')->select('id', 'repository_id')->get()->groupBy('repository_id');

        foreach ([1, 2] as $repoId) {
            $ids = $prIdsByRepo->get($repoId)?->pluck('id')->toArray() ?? [];
            if (empty($ids)) {
                continue;
            }
            foreach (array_slice($ids, 0, 20) as $prId) {
                $numFiles = rand(2, 8);
                $usedPaths = [];
                for ($f = 0; $f < $numFiles; $f++) {
                    $dir = $dirs[array_rand($dirs)];
                    $ext = $extensions[array_rand($extensions)];
                    $filePath = $dir . '/' . Str::random(8) . '.' . $ext;
                    if (in_array($filePath, $usedPaths)) {
                        continue;
                    }
                    $usedPaths[] = $filePath;
                    $type = $changeTypes[array_rand($changeTypes)];
                    $additions = rand(5, 200);
                    $deletions = $type !== 'added' ? rand(0, 150) : 0;
                    $changes = $additions + $deletions;
                    $causedFailure = (bool)rand(0, 6);
                    $rows[] = [
                        'repository_id' => $repoId,
                        'pull_request_id' => $prId,
                        'file_path' => $filePath,
                        'file_extension' => '.' . $ext,
                        'directory' => $dir,
                        'change_type' => $type,
                        'additions' => $additions,
                        'deletions' => $deletions,
                        'changes' => $changes,
                        'previous_file_path' => $type === 'renamed' ? $dir . '/OldName.' . $ext : null,
                        'caused_ci_failure' => $causedFailure,
                        'ci_failure_count' => $causedFailure ? rand(1, 5) : 0,
                        'failure_rate' => round(rand(0, 8000) / 100, 2),
                        'total_changes_count' => rand(1, 20),
                        'metadata' => json_encode([]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('file_changes')->insert($chunk);
        }
        $this->command->info('✓ Seeded ' . count($rows) . ' file changes');
    }

    private function seedWebhookEvents(): void
    {
        $this->command->info('✓ Skipping webhook_events (populated by real webhooks)');
    }
}
