<?php

declare(strict_types=1);

namespace App\Actions\PullRequest;

use App\Models\PullRequest;
use Illuminate\Support\Facades\Log;

/**
 * Update Pull Request Metrics Action
 *
 * Updates calculated metrics for a pull request based on related data.
 * Called after PR state changes or when reviews/CI status updates.
 *
 * @package App\Actions\PullRequest
 */
class UpdatePullRequestMetricsAction
{
    /**
     * Execute the action
     *
     * @param PullRequest $pullRequest
     * @return void
     */
    public function execute(PullRequest $pullRequest): void
    {
        // Calculate review metrics
        $this->updateReviewMetrics($pullRequest);

        // Calculate CI metrics
        $this->updateCIMetrics($pullRequest);

        // Calculate test metrics
        $this->updateTestMetrics($pullRequest);

        // Update overall status
        $this->updateOverallStatus($pullRequest);

        $pullRequest->save();

        Log::debug('Pull request metrics updated', [
            'pr_id' => $pullRequest->id,
            'review_status' => $pullRequest->review_status,
            'ci_status' => $pullRequest->ci_status,
        ]);
    }

    /**
     * Update review-related metrics
     *
     * @param PullRequest $pullRequest
     * @return void
     */
    private function updateReviewMetrics(PullRequest $pullRequest): void
    {
        // Count approvals from review events (this would be tracked separately)
        // For now, we'll use a simple calculation based on metadata
        // In a real implementation, this would query review events

        $pullRequest->approvals_count = $pullRequest->metadata['approvals_count'] ?? 0;

        // Determine review status
        if ($pullRequest->approvals_count > 0) {
            $pullRequest->review_status = 'approved';
        } elseif ($pullRequest->review_comments_count > 0) {
            $pullRequest->review_status = 'changes_requested';
        } else {
            $pullRequest->review_status = 'pending';
        }

        // Calculate time to first review (if not set)
        if (!$pullRequest->time_to_first_review && $pullRequest->first_review_at) {
            $pullRequest->time_to_first_review = $pullRequest->first_commit_at
                ? $pullRequest->first_commit_at->diffInSeconds($pullRequest->first_review_at)
                : null;
        }

        // Calculate time to approval
        if (!$pullRequest->time_to_approval && $pullRequest->approved_at) {
            $pullRequest->time_to_approval = $pullRequest->first_commit_at
                ? $pullRequest->first_commit_at->diffInSeconds($pullRequest->approved_at)
                : null;
        }
    }

    /**
     * Update CI-related metrics
     *
     * @param PullRequest $pullRequest
     * @return void
     */
    private function updateCIMetrics(PullRequest $pullRequest): void
    {
        // Get latest test run for this PR
        $latestTestRun = $pullRequest->testRuns()
            ->where('status', 'completed')
            ->latest()
            ->first();

        if ($latestTestRun) {
            $pullRequest->ci_status = $this->mapTestRunStatus($latestTestRun->status);
            $pullRequest->ci_checks_count = $latestTestRun->total_tests;
            $pullRequest->ci_checks_passed = $latestTestRun->passed_tests;
            $pullRequest->ci_checks_failed = $latestTestRun->failed_tests;
        } else {
            // No CI runs yet
            $pullRequest->ci_status = null;
            $pullRequest->ci_checks_count = 0;
            $pullRequest->ci_checks_passed = 0;
            $pullRequest->ci_checks_failed = 0;
        }
    }

    /**
     * Update test-related metrics
     *
     * @param PullRequest $pullRequest
     * @return void
     */
    private function updateTestMetrics(PullRequest $pullRequest): void
    {
        // Aggregate test metrics from all test runs for this PR
        $testRuns = $pullRequest->testRuns()
            ->where('status', 'completed')
            ->get();

        if ($testRuns->isNotEmpty()) {
            $pullRequest->tests_total = $testRuns->sum('total_tests');
            $pullRequest->tests_passed = $testRuns->sum('passed_tests');
            $pullRequest->tests_failed = $testRuns->sum('failed_tests');

            // Calculate average coverage
            $coverageValues = $testRuns->pluck('line_coverage')->filter()->values();
            $pullRequest->test_coverage = $coverageValues->isNotEmpty()
                ? $coverageValues->avg()
                : null;
        } else {
            $pullRequest->tests_total = 0;
            $pullRequest->tests_passed = 0;
            $pullRequest->tests_failed = 0;
            $pullRequest->test_coverage = null;
        }
    }

    /**
     * Update overall PR status and flags
     *
     * @param PullRequest $pullRequest
     * @return void
     */
    private function updateOverallStatus(PullRequest $pullRequest): void
    {
        // Mark as hot if it has many comments or large diff
        $pullRequest->is_hot = ($pullRequest->comments_count >= 10) ||
                               ($pullRequest->additions + $pullRequest->deletions >= 1000);

        // Update last activity timestamp
        $pullRequest->last_activity_at = now();
    }

    /**
     * Map test run status to CI status
     *
     * @param string $testRunStatus
     * @return string|null
     */
    private function mapTestRunStatus(string $testRunStatus): ?string
    {
        return match ($testRunStatus) {
            'success' => 'success',
            'failure' => 'failure',
            'cancelled' => 'cancelled',
            'skipped' => 'skipped',
            default => 'pending',
        };
    }
}