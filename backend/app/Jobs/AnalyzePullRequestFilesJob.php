<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\FileChange;
use App\Models\PullRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Analyze Pull Request Files Job
 *
 * Fetches and analyzes files changed in a pull request.
 * Calculates file change metrics and stores file change records.
 *
 * RETRY STRATEGY:
 * - Max attempts: 3
 * - Backoff: Exponential (1, 4, 16 seconds)
 *
 * @package App\Jobs
 */
class AnalyzePullRequestFilesJob implements ShouldQueue
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
    public int $timeout = 300; // 5 minutes

    /**
     * Pull request to analyze
     */
    private PullRequest $pullRequest;

    /**
     * Create a new job instance
     */
    public function __construct(PullRequest $pullRequest)
    {
        $this->pullRequest = $pullRequest;
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        Log::info('Starting PR file analysis', [
            'pr_id' => $this->pullRequest->id,
            'pr_number' => $this->pullRequest->number,
        ]);

        try {
            // Fetch files from GitHub API
            $files = $this->fetchPullRequestFiles();

            // Process and store file changes
            $this->processFileChanges($files);

            // Update PR with file analysis metrics
            $this->updatePullRequestMetrics($files);

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            Log::info('PR file analysis completed', [
                'pr_id' => $this->pullRequest->id,
                'files_count' => count($files),
                'duration_ms' => $duration,
            ]);

        } catch (Throwable $e) {
            Log::error('PR file analysis failed', [
                'pr_id' => $this->pullRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Fetch files changed in the pull request from GitHub API
     */
    private function fetchPullRequestFiles(): array
    {
        $githubToken = config('services.github.token');
        $repoFullName = $this->pullRequest->repository->full_name;

        if (!$githubToken || !$repoFullName) {
            throw new \Exception('GitHub token or repository name not configured');
        }

        $url = "https://api.github.com/repos/{$repoFullName}/pulls/{$this->pullRequest->number}/files";

        $response = Http::withToken($githubToken)
            ->withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'CI-Insights-Dashboard/1.0',
            ])
            ->timeout(30)
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception("GitHub API error: {$response->status()} - {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Process and store file changes
     */
    private function processFileChanges(array $files): void
    {
        foreach ($files as $fileData) {
            $this->createOrUpdateFileChange($fileData);
        }
    }

    /**
     * Create or update a file change record
     */
    private function createOrUpdateFileChange(array $fileData): void
    {
        $filename = $fileData['filename'];
        $status = $fileData['status']; // added, modified, removed, renamed

        // Find existing file change or create new
        $fileChange = FileChange::where('pull_request_id', $this->pullRequest->id)
            ->where('filename', $filename)
            ->first();

        if (!$fileChange) {
            $fileChange = new FileChange();
            $fileChange->pull_request_id = $this->pullRequest->id;
            $fileChange->filename = $filename;
        }

        // Update file change data
        $fileChange->status = $status;
        $fileChange->additions = $fileData['additions'] ?? 0;
        $fileChange->deletions = $fileData['deletions'] ?? 0;
        $fileChange->changes = $fileData['changes'] ?? 0;

        // File type analysis
        $fileChange->file_extension = $this->getFileExtension($filename);
        $fileChange->file_type = $this->classifyFileType($filename);

        // Code quality metrics (basic)
        $fileChange->is_test_file = $this->isTestFile($filename);
        $fileChange->is_config_file = $this->isConfigFile($filename);

        $fileChange->save();
    }

    /**
     * Update pull request with file analysis metrics
     */
    private function updatePullRequestMetrics(array $files): void
    {
        $metrics = $this->calculateFileMetrics($files);

        $this->pullRequest->update([
            'changed_files' => count($files),
            'additions' => $metrics['total_additions'],
            'deletions' => $metrics['total_deletions'],
            'metadata' => array_merge($this->pullRequest->metadata ?? [], [
                'file_metrics' => $metrics,
            ]),
        ]);
    }

    /**
     * Calculate aggregate file metrics
     */
    private function calculateFileMetrics(array $files): array
    {
        $totalAdditions = 0;
        $totalDeletions = 0;
        $fileTypes = [];
        $testFiles = 0;
        $configFiles = 0;

        foreach ($files as $file) {
            $totalAdditions += $file['additions'] ?? 0;
            $totalDeletions += $file['deletions'] ?? 0;

            $filename = $file['filename'];
            $type = $this->classifyFileType($filename);
            $fileTypes[$type] = ($fileTypes[$type] ?? 0) + 1;

            if ($this->isTestFile($filename)) {
                $testFiles++;
            }

            if ($this->isConfigFile($filename)) {
                $configFiles++;
            }
        }

        return [
            'total_additions' => $totalAdditions,
            'total_deletions' => $totalDeletions,
            'total_changes' => $totalAdditions + $totalDeletions,
            'file_types' => $fileTypes,
            'test_files_count' => $testFiles,
            'config_files_count' => $configFiles,
            'risk_score' => $this->calculateRiskScore($files),
        ];
    }

    /**
     * Calculate risk score based on files changed
     */
    private function calculateRiskScore(array $files): float
    {
        $score = 0;

        foreach ($files as $file) {
            $filename = $file['filename'];
            $changes = ($file['additions'] ?? 0) + ($file['deletions'] ?? 0);

            // Higher risk for core files
            if ($this->isCoreFile($filename)) {
                $score += $changes * 2;
            } elseif ($this->isConfigFile($filename)) {
                $score += $changes * 1.5;
            } else {
                $score += $changes;
            }
        }

        // Normalize score (rough heuristic)
        return min($score / 1000, 10);
    }

    /**
     * Get file extension
     */
    private function getFileExtension(string $filename): ?string
    {
        $parts = explode('.', $filename);
        return count($parts) > 1 ? end($parts) : null;
    }

    /**
     * Classify file type based on extension
     */
    private function classifyFileType(string $filename): string
    {
        $extension = strtolower($this->getFileExtension($filename) ?? '');

        return match ($extension) {
            'php', 'py', 'js', 'ts', 'java', 'c', 'cpp', 'cs', 'rb', 'go', 'rs' => 'code',
            'html', 'css', 'scss', 'less' => 'frontend',
            'sql', 'yml', 'yaml', 'json', 'xml' => 'config',
            'md', 'txt', 'rst' => 'documentation',
            'jpg', 'png', 'gif', 'svg' => 'image',
            default => 'other',
        };
    }

    /**
     * Check if file is a test file
     */
    private function isTestFile(string $filename): bool
    {
        $lower = strtolower($filename);
        return str_contains($lower, 'test') ||
               str_contains($lower, 'spec') ||
               str_ends_with($lower, 'test.php') ||
               str_ends_with($lower, 'spec.php');
    }

    /**
     * Check if file is a config file
     */
    private function isConfigFile(string $filename): bool
    {
        $lower = strtolower($filename);
        return str_contains($lower, 'config') ||
               str_contains($lower, '.env') ||
               in_array($this->getFileExtension($filename), ['yml', 'yaml', 'json', 'xml', 'ini', 'conf']);
    }

    /**
     * Check if file is a core application file
     */
    private function isCoreFile(string $filename): bool
    {
        $lower = strtolower($filename);
        return str_contains($lower, 'core') ||
               str_contains($lower, 'app/') ||
               str_contains($lower, 'src/') ||
               str_starts_with($lower, 'lib/');
    }

    /**
     * Handle job failure
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('PR file analysis job failed permanently', [
            'pr_id' => $this->pullRequest->id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Could trigger an alert here
    }

    /**
     * Get job tags for Horizon UI
     */
    public function tags(): array
    {
        return [
            'analyze-files',
            "pr:{$this->pullRequest->id}",
            "repo:{$this->pullRequest->repository_id}",
        ];
    }
}