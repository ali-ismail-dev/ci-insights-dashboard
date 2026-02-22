<?php

declare(strict_types=1);

namespace App\Actions\PullRequest;

use App\Models\FileChange;
use App\Models\PullRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analyze Pull Request Files Action
 *
 * Fetches and analyzes files changed in a pull request.
 * Calculates file change metrics and stores file change records.
 *
 * @package App\Actions\PullRequest
 */
class AnalyzePullRequestFilesAction
{
    /**
     * Execute the action
     *
     * @param PullRequest $pullRequest
     * @return void
     */
    public function execute(PullRequest $pullRequest): void
    {
        Log::info('Starting PR file analysis', [
            'pr_id' => $pullRequest->id,
            'pr_number' => $pullRequest->number,
        ]);

        // Fetch files from GitHub API
        $files = $this->fetchPullRequestFiles($pullRequest);

        // Process and store file changes
        $this->processFileChanges($files, $pullRequest);

        // Update PR with file analysis metrics
        $this->updatePullRequestMetrics($files, $pullRequest);

        Log::info('PR file analysis completed', [
            'pr_id' => $pullRequest->id,
            'files_count' => count($files),
        ]);
    }

    /**
     * Fetch files changed in the pull request from GitHub API
     *
     * @param PullRequest $pullRequest
     * @return array
     */
    private function fetchPullRequestFiles(PullRequest $pullRequest): array
    {
        $githubToken = config('services.github.token');
        $repoFullName = $pullRequest->repository->full_name;

        if (!$githubToken || !$repoFullName) {
            throw new \Exception('GitHub token or repository name not configured');
        }

        $url = "https://api.github.com/repos/{$repoFullName}/pulls/{$pullRequest->number}/files";

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
     *
     * @param array $files
     * @param PullRequest $pullRequest
     * @return void
     */
    private function processFileChanges(array $files, PullRequest $pullRequest): void
    {
        foreach ($files as $fileData) {
            $this->createOrUpdateFileChange($fileData, $pullRequest);
        }
    }

    /**
     * Create or update a file change record
     *
     * @param array $fileData
     * @param PullRequest $pullRequest
     * @return void
     */
    private function createOrUpdateFileChange(array $fileData, PullRequest $pullRequest): void
    {
        $filename = $fileData['filename'];
        $status = $fileData['status']; // added, modified, removed, renamed

        // Find existing file change or create new
        $fileChange = FileChange::where('pull_request_id', $pullRequest->id)
            ->where('filename', $filename)
            ->first();

        if (!$fileChange) {
            $fileChange = new FileChange();
            $fileChange->pull_request_id = $pullRequest->id;
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
     *
     * @param array $files
     * @param PullRequest $pullRequest
     * @return void
     */
    private function updatePullRequestMetrics(array $files, PullRequest $pullRequest): void
    {
        $metrics = $this->calculateFileMetrics($files);

        $pullRequest->update([
            'changed_files' => count($files),
            'additions' => $metrics['total_additions'],
            'deletions' => $metrics['total_deletions'],
            'metadata' => array_merge($pullRequest->metadata ?? [], [
                'file_metrics' => $metrics,
            ]),
        ]);
    }

    /**
     * Calculate aggregate file metrics
     *
     * @param array $files
     * @return array
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
     *
     * @param array $files
     * @return float
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
     *
     * @param string $filename
     * @return string|null
     */
    private function getFileExtension(string $filename): ?string
    {
        $parts = explode('.', $filename);
        return count($parts) > 1 ? end($parts) : null;
    }

    /**
     * Classify file type based on extension
     *
     * @param string $filename
     * @return string
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
     *
     * @param string $filename
     * @return bool
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
     *
     * @param string $filename
     * @return bool
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
     *
     * @param string $filename
     * @return bool
     */
    private function isCoreFile(string $filename): bool
    {
        $lower = strtolower($filename);
        return str_contains($lower, 'core') ||
               str_contains($lower, 'app/') ||
               str_contains($lower, 'src/') ||
               str_starts_with($lower, 'lib/');
    }
}