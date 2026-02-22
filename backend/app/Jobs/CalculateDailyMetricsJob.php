<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DailyMetric;
use App\Models\Repository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Calculate Daily Metrics Job
 *
 * Aggregates daily statistics for repositories including PR metrics,
 * test metrics, and CI performance. Runs daily via scheduler.
 *
 * RETRY STRATEGY:
 * - Max attempts: 3
 * - Backoff: Exponential (1, 4, 16 seconds)
 *
 * @package App\Jobs
 */
class CalculateDailyMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of retry attempts
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying
     */
    public array $backoff = [1, 4, 16];

    /**
     * Maximum number of seconds the job should run
     */
    public int $timeout = 900; // 15 minutes

    /**
     * Target date for metrics calculation (defaults to yesterday)
     */
    private ?string $targetDate;

    /**
     * Specific repository ID (optional, for single repo processing)
     */
    private ?int $repositoryId;

    /**
     * Create a new job instance
     */
    public function __construct(?string $targetDate = null, ?int $repositoryId = null)
    {
        $this->targetDate = $targetDate ?? now()->subDay()->toDateString();
        $this->repositoryId = $repositoryId;
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        Log::info('Starting daily metrics calculation', [
            'target_date' => $this->targetDate,
            'repository_id' => $this->repositoryId,
        ]);

        try {
            $repositories = $this->getRepositoriesToProcess();

            foreach ($repositories as $repository) {
                $this->calculateRepositoryMetrics($repository);
            }

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            Log::info('Daily metrics calculation completed', [
                'target_date' => $this->targetDate,
                'repositories_processed' => $repositories->count(),
                'duration_ms' => $duration,
            ]);

        } catch (Throwable $e) {
            Log::error('Daily metrics calculation failed', [
                'target_date' => $this->targetDate,
                'repository_id' => $this->repositoryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get repositories to process
     */
    private function getRepositoriesToProcess()
    {
        $query = Repository::query();

        if ($this->repositoryId) {
            $query->where('id', $this->repositoryId);
        }

        return $query->get();
    }

    /**
     * Calculate metrics for a specific repository
     */
    private function calculateRepositoryMetrics(Repository $repository): void
    {
        $date = $this->targetDate;

        Log::debug('Calculating metrics for repository', [
            'repository_id' => $repository->id,
            'date' => $date,
        ]);

        // Calculate all metrics in a transaction
        DB::transaction(function () use ($repository, $date) {
            $metrics = $this->gatherMetrics($repository, $date);

            // Create or update daily metric record
            DailyMetric::updateOrCreate(
                [
                    'repository_id' => $repository->id,
                    'date' => $date,
                ],
                $metrics
            );
        });
    }

    /**
     * Gather all metrics for the repository and date
     */
    private function gatherMetrics(Repository $repository, string $date): array
    {
        return [
            // Pull Request metrics
            'prs_opened' => $this->countPullRequestsOpened($repository, $date),
            'prs_closed' => $this->countPullRequestsClosed($repository, $date),
            'prs_merged' => $this->countPullRequestsMerged($repository, $date),
            'open_prs_count' => $this->countOpenPullRequests($repository, $date),

            // Test metrics
            'tests_run' => $this->countTestRuns($repository, $date),
            'tests_passed' => $this->countTestsPassed($repository, $date),
            'tests_failed' => $this->countTestsFailed($repository, $date),
            'flaky_tests_detected' => $this->countFlakyTestsDetected($repository, $date),

            // CI/CD metrics
            'ci_builds_total' => $this->countCIBuilds($repository, $date),
            'ci_builds_passed' => $this->countCIBuildsPassed($repository, $date),
            'ci_builds_failed' => $this->countCIBuildsFailed($repository, $date),
            'avg_ci_duration' => $this->calculateAverageCIDuration($repository, $date),

            // Code quality metrics
            'code_coverage_avg' => $this->calculateAverageCodeCoverage($repository, $date),
            'lines_added' => $this->countLinesAdded($repository, $date),
            'lines_deleted' => $this->countLinesDeleted($repository, $date),

            // Time metrics
            'avg_cycle_time' => $this->calculateAverageCycleTime($repository, $date),
            'avg_time_to_review' => $this->calculateAverageTimeToReview($repository, $date),
            'avg_time_to_merge' => $this->calculateAverageTimeToMerge($repository, $date),

            // Alert metrics
            'alerts_created' => $this->countAlertsCreated($repository, $date),
            'alerts_resolved' => $this->countAlertsResolved($repository, $date),
            'active_alerts_count' => $this->countActiveAlerts($repository, $date),
        ];
    }

    // PR Metrics
    private function countPullRequestsOpened(Repository $repository, string $date): int
    {
        return $repository->pullRequests()
            ->whereDate('created_at', $date)
            ->count();
    }

    private function countPullRequestsClosed(Repository $repository, string $date): int
    {
        return $repository->pullRequests()
            ->whereDate('closed_at', $date)
            ->count();
    }

    private function countPullRequestsMerged(Repository $repository, string $date): int
    {
        return $repository->pullRequests()
            ->whereDate('merged_at', $date)
            ->count();
    }

    private function countOpenPullRequests(Repository $repository, string $date): int
    {
        return $repository->pullRequests()
            ->where('state', 'open')
            ->where('created_at', '<=', $date . ' 23:59:59')
            ->where(function ($query) use ($date) {
                $query->where('closed_at', '>', $date . ' 23:59:59')
                      ->orWhereNull('closed_at');
            })
            ->count();
    }

    // Test Metrics
    private function countTestRuns(Repository $repository, string $date): int
    {
        return $repository->testRuns()
            ->whereDate('created_at', $date)
            ->count();
    }

    private function countTestsPassed(Repository $repository, string $date): int
    {
        return $repository->testRuns()
            ->whereDate('created_at', $date)
            ->sum('passed_tests');
    }

    private function countTestsFailed(Repository $repository, string $date): int
    {
        return $repository->testRuns()
            ->whereDate('created_at', $date)
            ->sum('failed_tests');
    }

    private function countFlakyTestsDetected(Repository $repository, string $date): int
    {
        return $repository->testRuns()
            ->whereDate('created_at', $date)
            ->sum('flaky_tests');
    }

    // CI/CD Metrics
    private function countCIBuilds(Repository $repository, string $date): int
    {
        return $repository->testRuns()
            ->whereDate('created_at', $date)
            ->where('status', 'completed')
            ->count();
    }

    private function countCIBuildsPassed(Repository $repository, string $date): int
    {
        return $repository->testRuns()
            ->whereDate('created_at', $date)
            ->where('status', 'success')
            ->count();
    }

    private function countCIBuildsFailed(Repository $repository, string $date): int
    {
        return $repository->testRuns()
            ->whereDate('created_at', $date)
            ->whereIn('status', ['failure', 'error'])
            ->count();
    }

    private function calculateAverageCIDuration(Repository $repository, string $date): ?int
    {
        $avgDuration = $repository->testRuns()
            ->whereDate('created_at', $date)
            ->whereNotNull('duration')
            ->avg('duration');

        return $avgDuration ? (int) $avgDuration : null;
    }

    // Code Quality Metrics
    private function calculateAverageCodeCoverage(Repository $repository, string $date): ?float
    {
        $avgCoverage = $repository->testRuns()
            ->whereDate('created_at', $date)
            ->whereNotNull('line_coverage')
            ->avg('line_coverage');

        return $avgCoverage ? round($avgCoverage, 2) : null;
    }

    private function countLinesAdded(Repository $repository, string $date): int
    {
        return $repository->pullRequests()
            ->whereDate('merged_at', $date)
            ->sum('additions');
    }

    private function countLinesDeleted(Repository $repository, string $date): int
    {
        return $repository->pullRequests()
            ->whereDate('merged_at', $date)
            ->sum('deletions');
    }

    // Time Metrics
    private function calculateAverageCycleTime(Repository $repository, string $date): ?int
    {
        $avgCycleTime = $repository->pullRequests()
            ->whereDate('merged_at', $date)
            ->whereNotNull('cycle_time')
            ->avg('cycle_time');

        return $avgCycleTime ? (int) $avgCycleTime : null;
    }

    private function calculateAverageTimeToReview(Repository $repository, string $date): ?int
    {
        $avgTimeToReview = $repository->pullRequests()
            ->whereDate('merged_at', $date)
            ->whereNotNull('time_to_first_review')
            ->avg('time_to_first_review');

        return $avgTimeToReview ? (int) $avgTimeToReview : null;
    }

    private function calculateAverageTimeToMerge(Repository $repository, string $date): ?int
    {
        $avgTimeToMerge = $repository->pullRequests()
            ->whereDate('merged_at', $date)
            ->whereNotNull('time_to_merge')
            ->avg('time_to_merge');

        return $avgTimeToMerge ? (int) $avgTimeToMerge : null;
    }

    // Alert Metrics
    private function countAlertsCreated(Repository $repository, string $date): int
    {
        return $repository->alerts()
            ->whereDate('created_at', $date)
            ->count();
    }

    private function countAlertsResolved(Repository $repository, string $date): int
    {
        return $repository->alerts()
            ->whereDate('resolved_at', $date)
            ->count();
    }

    private function countActiveAlerts(Repository $repository, string $date): int
    {
        return $repository->alerts()
            ->where('status', 'active')
            ->where('created_at', '<=', $date . ' 23:59:59')
            ->where(function ($query) use ($date) {
                $query->where('resolved_at', '>', $date . ' 23:59:59')
                      ->orWhereNull('resolved_at');
            })
            ->count();
    }

    /**
     * Handle job failure
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('Daily metrics calculation job failed permanently', [
            'target_date' => $this->targetDate,
            'repository_id' => $this->repositoryId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get job tags for Horizon UI
     */
    public function tags(): array
    {
        return [
            'daily-metrics',
            'date:' . $this->targetDate,
            $this->repositoryId ? "repo:{$this->repositoryId}" : 'all-repos',
        ];
    }
}