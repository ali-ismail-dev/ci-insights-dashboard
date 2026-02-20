<?php

declare(strict_types=1);

namespace App\Actions\TestRun;

use App\Models\PullRequest;
use App\Models\TestRun;
use Illuminate\Support\Facades\Log;

/**
 * Process Test Run Action
 * 
 * Creates or updates test run from CI webhook payload.
 * Parses test results and calculates coverage metrics.
 * 
 * @package App\Actions\TestRun
 */
class ProcessTestRunAction
{
    /**
     * Execute the action
     * 
     * @param int $repositoryId
     * @param array $testRunData CI run data from webhook
     * @param string $eventType check_run, check_suite, or workflow_run
     * @return TestRun|null
     */
    public function execute(
        int $repositoryId,
        array $testRunData,
        string $eventType
    ): ?TestRun {
        // Extract common fields
        $externalId = $testRunData['id'] ?? null;
        $name = $testRunData['name'] ?? 'Unknown';
        $conclusion = $testRunData['conclusion'] ?? null;
        $headSha = $testRunData['head_sha'] ?? $testRunData['head_commit']['id'] ?? null;
        
        if (!$externalId || !$headSha) {
            Log::warning('Missing required test run data', [
                'repository_id' => $repositoryId,
                'event_type' => $eventType,
            ]);
            return null;
        }
        
        // Map CI provider
        $ciProvider = $this->determineCIProvider($eventType, $testRunData);
        
        // Map conclusion to status
        $status = $this->mapConclusionToStatus($conclusion);
        
        // Find associated PR
        $pullRequest = PullRequest::where('repository_id', $repositoryId)
            ->where('head_sha', $headSha)
            ->first();
        
        // Extract test results (if available)
        $testResults = $this->extractTestResults($testRunData);
        
        // Create or update test run
        $testRun = TestRun::updateOrCreate(
            [
                'repository_id' => $repositoryId,
                'external_id' => (string) $externalId,
                'ci_provider' => $ciProvider,
            ],
            [
                'pull_request_id' => $pullRequest?->id,
                'workflow_name' => $name,
                'job_name' => $testRunData['details_url'] ?? null,
                'branch' => $testRunData['head_branch'] ?? $pullRequest?->head_branch ?? 'unknown',
                'commit_sha' => $headSha,
                'status' => $status,
                'total_tests' => $testResults['total'] ?? 0,
                'passed_tests' => $testResults['passed'] ?? 0,
                'failed_tests' => $testResults['failed'] ?? 0,
                'skipped_tests' => $testResults['skipped'] ?? 0,
                'flaky_tests' => $testResults['flaky'] ?? 0,
                'line_coverage' => $testResults['coverage']['line'] ?? null,
                'branch_coverage' => $testResults['coverage']['branch'] ?? null,
                'method_coverage' => $testResults['coverage']['method'] ?? null,
                'duration' => $this->calculateDuration($testRunData),
                'started_at' => $this->parseTimestamp($testRunData['started_at'] ?? null),
                'completed_at' => $this->parseTimestamp($testRunData['completed_at'] ?? null),
                'failed_tests_details' => $testResults['failed_details'] ?? null,
                'flaky_tests_details' => $testResults['flaky_details'] ?? null,
                'run_url' => $testRunData['html_url'] ?? $testRunData['details_url'] ?? null,
                'logs_url' => $testRunData['logs_url'] ?? null,
            ]
        );
        
        Log::info('Test run processed', [
            'test_run_id' => $testRun->id,
            'repository_id' => $repositoryId,
            'pr_id' => $pullRequest?->id,
            'status' => $status,
            'total_tests' => $testResults['total'] ?? 0,
        ]);
        
        return $testRun;
    }
    
    /**
     * Determine CI provider from event type and data
     * 
     * @param string $eventType
     * @param array $data
     * @return string
     */
    private function determineCIProvider(string $eventType, array $data): string
    {
        // GitHub Actions
        if ($eventType === 'workflow_run') {
            return 'github_actions';
        }
        
        // Check run / suite can be from various providers
        $app = $data['app']['name'] ?? null;
        
        return match ($app) {
            'GitHub Actions' => 'github_actions',
            'CircleCI' => 'circleci',
            'Travis CI' => 'travis',
            'Jenkins' => 'jenkins',
            default => 'unknown',
        };
    }
    
    /**
     * Map GitHub conclusion to our status
     * 
     * @param string|null $conclusion
     * @return string
     */
    private function mapConclusionToStatus(?string $conclusion): string
    {
        return match ($conclusion) {
            'success' => 'success',
            'failure' => 'failure',
            'neutral' => 'success',
            'cancelled' => 'canceled',
            'skipped' => 'skipped',
            'timed_out' => 'error',
            'action_required' => 'error',
            default => 'pending',
        };
    }
    
    /**
     * Extract test results from payload
     * 
     * Note: GitHub webhooks don't include detailed test results by default.
     * This would require fetching from GitHub API or parsing artifacts.
     * 
     * @param array $data
     * @return array
     */
    private function extractTestResults(array $data): array
    {
        // Placeholder: In production, fetch from GitHub API or parse logs
        // For now, return empty structure
        
        return [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'flaky' => 0,
            'coverage' => [
                'line' => null,
                'branch' => null,
                'method' => null,
            ],
            'failed_details' => null,
            'flaky_details' => null,
        ];
    }
    
    /**
     * Calculate test run duration from timestamps
     * 
     * @param array $data
     * @return int|null Duration in seconds
     */
    private function calculateDuration(array $data): ?int
    {
        $startedAt = $this->parseTimestamp($data['started_at'] ?? null);
        $completedAt = $this->parseTimestamp($data['completed_at'] ?? null);
        
        if ($startedAt && $completedAt) {
            return $completedAt->diffInSeconds($startedAt);
        }
        
        return null;
    }
    
    /**
     * Parse ISO 8601 timestamp
     * 
     * @param string|null $timestamp
     * @return \Illuminate\Support\Carbon|null
     */
    private function parseTimestamp(?string $timestamp): ?\Illuminate\Support\Carbon
    {
        if (!$timestamp) {
            return null;
        }
        
        try {
            return \Illuminate\Support\Carbon::parse($timestamp);
        } catch (\Exception $e) {
            return null;
        }
    }
}