<?php

declare(strict_types=1);

namespace App\Actions\Alert;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\TestResult;
use App\Models\TestRun;
use Illuminate\Support\Facades\Log;

/**
 * Detect Flaky Tests Action
 *
 * Analyzes test runs to identify flaky tests that pass/fail intermittently.
 * Creates alerts for tests that fail in some runs but pass in others.
 *
 * @package App\Actions\Alert
 */
class DetectFlakyTestsAction
{
    /**
     * Execute the action
     *
     * @param TestRun $testRun
     * @return void
     */
    public function execute(TestRun $testRun): void
    {
        Log::info('Starting flaky test detection', [
            'test_run_id' => $testRun->id,
            'repository_id' => $testRun->repository_id,
            'failed_tests' => $testRun->failed_tests,
        ]);

        // Only analyze if there were failures
        if ($testRun->failed_tests === 0) {
            Log::debug('Skipping flaky test detection - no failures', [
                'test_run_id' => $testRun->id,
            ]);
            return;
        }

        // Analyze failed tests for flakiness
        $flakyTests = $this->analyzeFlakyTests($testRun);

        // Create alerts for flaky tests
        $this->createFlakyTestAlerts($flakyTests, $testRun);

        // Update test run with flaky test count
        $testRun->update([
            'flaky_tests' => count($flakyTests),
            'flaky_tests_details' => $flakyTests,
        ]);

        Log::info('Flaky test detection completed', [
            'test_run_id' => $testRun->id,
            'flaky_tests_found' => count($flakyTests),
        ]);
    }

    /**
     * Analyze failed tests to determine which are flaky
     *
     * @param TestRun $testRun
     * @return array
     */
    private function analyzeFlakyTests(TestRun $testRun): array
    {
        $flakyTests = [];

        // Get failed test results from this run
        $failedTests = TestResult::where('test_run_id', $testRun->id)
            ->where('status', 'failed')
            ->get();

        foreach ($failedTests as $failedTest) {
            $flakinessData = $this->calculateTestFlakiness($failedTest, $testRun);

            if ($flakinessData['is_flaky']) {
                $flakyTests[] = [
                    'test_name' => $failedTest->test_name,
                    'test_class' => $failedTest->test_class,
                    'failure_rate' => $flakinessData['failure_rate'],
                    'total_runs' => $flakinessData['total_runs'],
                    'recent_failures' => $flakinessData['recent_failures'],
                    'confidence_score' => $flakinessData['confidence_score'],
                ];
            }
        }

        return $flakyTests;
    }

    /**
     * Calculate flakiness metrics for a specific test
     *
     * @param TestResult $failedTest
     * @param TestRun $testRun
     * @return array
     */
    private function calculateTestFlakiness(TestResult $failedTest, TestRun $testRun): array
    {
        // Look for the same test in recent runs (last 30 days, max 50 runs)
        $recentResults = TestResult::where('test_name', $failedTest->test_name)
            ->where('test_class', $failedTest->test_class)
            ->where('repository_id', $testRun->repository_id)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('test_run_id', '!=', $testRun->id) // Exclude current run
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        if ($recentResults->isEmpty()) {
            return [
                'is_flaky' => false,
                'failure_rate' => 0,
                'total_runs' => 0,
                'recent_failures' => 0,
                'confidence_score' => 0,
            ];
        }

        $totalRuns = $recentResults->count();
        $failures = $recentResults->where('status', 'failed')->count();
        $failureRate = $failures / $totalRuns;

        // Consider flaky if failure rate is between 5% and 95%
        // (not always failing, not always passing)
        $isFlaky = $failureRate >= 0.05 && $failureRate <= 0.95 && $totalRuns >= 5;

        // Calculate confidence score based on sample size and failure rate
        $confidenceScore = $this->calculateConfidenceScore($failureRate, $totalRuns);

        return [
            'is_flaky' => $isFlaky,
            'failure_rate' => round($failureRate * 100, 2),
            'total_runs' => $totalRuns,
            'recent_failures' => $failures,
            'confidence_score' => round($confidenceScore, 2),
        ];
    }

    /**
     * Calculate confidence score for flakiness detection
     *
     * @param float $failureRate
     * @param int $totalRuns
     * @return float
     */
    private function calculateConfidenceScore(float $failureRate, int $totalRuns): float
    {
        // Simple confidence calculation based on sample size and deviation from 0.5
        $sampleConfidence = min($totalRuns / 20, 1); // Max confidence at 20+ samples
        $rateConfidence = 1 - abs($failureRate - 0.5) * 2; // Higher confidence when rate is closer to 50%

        return ($sampleConfidence + $rateConfidence) / 2;
    }

    /**
     * Create alerts for detected flaky tests
     *
     * @param array $flakyTests
     * @param TestRun $testRun
     * @return void
     */
    private function createFlakyTestAlerts(array $flakyTests, TestRun $testRun): void
    {
        // Get or create the flaky test alert rule
        $alertRule = $this->getFlakyTestAlertRule();

        foreach ($flakyTests as $flakyTest) {
            // Check if alert already exists for this test
            $existingAlert = Alert::where('alert_rule_id', $alertRule->id)
                ->where('repository_id', $testRun->repository_id)
                ->where('pull_request_id', $testRun->pull_request_id)
                ->where('fingerprint', $this->generateTestFingerprint($flakyTest, $testRun))
                ->where('status', '!=', 'resolved')
                ->first();

            if ($existingAlert) {
                // Update existing alert
                $existingAlert->update([
                    'occurrence_count' => $existingAlert->occurrence_count + 1,
                    'context' => array_merge($existingAlert->context ?? [], [
                        'latest_failure_rate' => $flakyTest['failure_rate'],
                        'latest_runs' => $flakyTest['total_runs'],
                        'last_detected_at' => now(),
                    ]),
                ]);
            } else {
                // Create new alert
                Alert::create([
                    'alert_rule_id' => $alertRule->id,
                    'repository_id' => $testRun->repository_id,
                    'pull_request_id' => $testRun->pull_request_id,
                    'alert_type' => 'flaky_test',
                    'severity' => $this->determineSeverity($flakyTest),
                    'title' => "Flaky Test: {$flakyTest['test_name']}",
                    'message' => $this->generateAlertMessage($flakyTest),
                    'context' => [
                        'test_name' => $flakyTest['test_name'],
                        'test_class' => $flakyTest['test_class'],
                        'failure_rate' => $flakyTest['failure_rate'],
                        'total_runs' => $flakyTest['total_runs'],
                        'confidence_score' => $flakyTest['confidence_score'],
                        'test_run_id' => $testRun->id,
                    ],
                    'status' => 'active',
                    'fingerprint' => $this->generateTestFingerprint($flakyTest, $testRun),
                    'occurrence_count' => 1,
                ]);
            }
        }
    }

    /**
     * Get or create the flaky test alert rule
     *
     * @return AlertRule
     */
    private function getFlakyTestAlertRule(): AlertRule
    {
        return AlertRule::firstOrCreate(
            ['code' => 'flaky_test_detected'],
            [
                'name' => 'Flaky Test Detected',
                'description' => 'Test that fails intermittently across multiple runs',
                'severity' => 'medium',
                'is_enabled' => true,
                'conditions' => [
                    'failure_rate_min' => 5,
                    'failure_rate_max' => 95,
                    'min_sample_size' => 5,
                ],
                'actions' => [
                    'create_alert' => true,
                    'notify_channels' => ['slack', 'email'],
                ],
            ]
        );
    }

    /**
     * Determine alert severity based on flakiness data
     *
     * @param array $flakyTest
     * @return string
     */
    private function determineSeverity(array $flakyTest): string
    {
        $failureRate = $flakyTest['failure_rate'];
        $confidence = $flakyTest['confidence_score'];

        if ($failureRate > 30 && $confidence > 0.8) {
            return 'high';
        } elseif ($failureRate > 15 && $confidence > 0.6) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Generate alert message for flaky test
     *
     * @param array $flakyTest
     * @return string
     */
    private function generateAlertMessage(array $flakyTest): string
    {
        return "Test '{$flakyTest['test_name']}' has failed in {$flakyTest['failure_rate']}% " .
               "of the last {$flakyTest['total_runs']} runs, indicating flakiness. " .
               "Confidence score: {$flakyTest['confidence_score']}/1.0";
    }

    /**
     * Generate unique fingerprint for test alert
     *
     * @param array $flakyTest
     * @param TestRun $testRun
     * @return string
     */
    private function generateTestFingerprint(array $flakyTest, TestRun $testRun): string
    {
        return 'flaky_test_' . md5(
            $testRun->repository_id . '_' .
            $flakyTest['test_class'] . '_' .
            $flakyTest['test_name']
        );
    }
}