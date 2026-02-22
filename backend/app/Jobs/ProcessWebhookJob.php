<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Alert\DetectFlakyTestsAction;
use App\Actions\PullRequest\AnalyzePullRequestFilesAction;
use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Process Webhook Job
 * 
 * Processes stored webhook events asynchronously.
 * Delegates to specific actions based on event type.
 * 
 * RETRY STRATEGY:
 * - Max attempts: 3
 * - Backoff: Exponential (1, 4, 16 seconds)
 * - Failed jobs → failed_jobs table for manual retry
 * 
 * @package App\Jobs
 */
class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /**
     * Maximum number of retry attempts
     * 
     * @var int
     */
    public int $tries = 3;
    
    /**
     * Number of seconds to wait before retrying
     * 
     * Exponential backoff: [1, 4, 16]
     * 
     * @var array
     */
    public array $backoff = [1, 4, 16];
    
    /**
     * Maximum number of seconds the job should run
     * 
     * @var int
     */
    public int $timeout = 180; // 3 minutes
    
    /**
     * Webhook event to process
     * 
     * @var WebhookEvent
     */
    private WebhookEvent $event;
    
    /**
     * Create a new job instance
     * 
     * @param WebhookEvent $event
     */
    public function __construct(WebhookEvent $event)
    {
        $this->event = $event;
        
        // Set job tags for Horizon UI
        $this->onQueue($this->determineQueue());
    }
    
    /**
     * Execute the job
     * 
     * @return void
     * @throws Throwable
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        // Update status to processing
        $this->event->update([
            'status' => 'processing',
        ]);
        
        Log::info('Processing webhook event', [
            'event_id' => $this->event->id,
            'delivery_id' => $this->event->delivery_id,
            'event_type' => $this->event->event_type,
            'action' => $this->event->action,
            'attempt' => $this->attempts(),
        ]);
        
        try {
            // Process based on event type
            $this->processEvent();
            
            // Calculate processing duration
            $duration = (int) ((microtime(true) - $startTime) * 1000); // milliseconds
            
            // Mark as completed
            $this->event->update([
                'status' => 'completed',
                'processed_at' => now(),
                'processing_duration' => $duration,
                'error_message' => null,
            ]);
            
            Log::info('Webhook event processed successfully', [
                'event_id' => $this->event->id,
                'duration_ms' => $duration,
            ]);
            
        } catch (Throwable $e) {
            // Log error
            Log::error('Webhook processing failed', [
                'event_id' => $this->event->id,
                'delivery_id' => $this->event->delivery_id,
                'event_type' => $this->event->event_type,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Update event with error
            $this->event->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count' => $this->attempts(),
            ]);
            
            // Re-throw to trigger retry
            throw $e;
        }
    }
    
    /**
     * Process webhook event based on type
     * 
     * @return void
     * @throws \Exception
     */
    private function processEvent(): void
    {
        $payload = $this->event->payload;
        
        switch ($this->event->event_type) {
            case 'pull_request':
                $this->processPullRequestEvent($payload);
                break;
                
            case 'pull_request_review':
                $this->processPullRequestReviewEvent($payload);
                break;
                
            case 'check_run':
            case 'check_suite':
            case 'workflow_run':
                $this->processCheckEvent($payload);
                break;
                
            case 'status':
                $this->processStatusEvent($payload);
                break;
                
            case 'push':
                $this->processPushEvent($payload);
                break;
                
            default:
                Log::warning('Unsupported webhook event type', [
                    'event_id' => $this->event->id,
                    'event_type' => $this->event->event_type,
                ]);
                
                $this->event->update(['status' => 'skipped']);
                break;
        }
    }
    
    /**
     * Process pull_request webhook event
     * 
     * Actions: opened, closed, reopened, synchronize, edited
     * 
     * @param array $payload
     * @return void
     */
    private function processPullRequestEvent(array $payload): void
    {
        $action = $this->event->action;
        $prData = $payload['pull_request'] ?? null;
        
        if (!$prData) {
            throw new \Exception('Missing pull_request data in payload');
        }
        
        Log::debug('Processing pull_request event', [
            'event_id' => $this->event->id,
            'action' => $action,
            'pr_number' => $prData['number'] ?? null,
        ]);
        
        // Create or update pull request
        $pullRequest = app(CreateOrUpdatePullRequestAction::class)->execute(
            $this->event->repository_id,
            $prData
        );
        
        // Update metrics if PR state changed
        if (in_array($action, ['opened', 'closed', 'reopened'], true)) {
            app(UpdatePullRequestMetricsAction::class)->execute($pullRequest);
        }
        
        // Queue file changes analysis if synchronized (new commits)
        if ($action === 'synchronize') {
            app(AnalyzePullRequestFilesAction::class)->execute($pullRequest);
        }
        
        Log::info('Pull request processed', [
            'event_id' => $this->event->id,
            'pr_id' => $pullRequest->id,
            'pr_number' => $pullRequest->number,
            'action' => $action,
        ]);
    }
    
    /**
     * Process pull_request_review webhook event
     * 
     * Actions: submitted, edited, dismissed
     * 
     * @param array $payload
     * @return void
     */
    private function processPullRequestReviewEvent(array $payload): void
    {
        $reviewData = $payload['review'] ?? null;
        $prData = $payload['pull_request'] ?? null;
        
        if (!$reviewData || !$prData) {
            throw new \Exception('Missing review or pull_request data in payload');
        }
        
        // Update PR review metrics
        $pullRequest = app(CreateOrUpdatePullRequestAction::class)->execute(
            $this->event->repository_id,
            $prData
        );
        
        // Update review status and counts
        app(UpdatePullRequestMetricsAction::class)->execute($pullRequest);
        
        Log::info('Pull request review processed', [
            'event_id' => $this->event->id,
            'pr_id' => $pullRequest->id,
            'review_state' => $reviewData['state'] ?? null,
        ]);
    }
    
    /**
     * Process check_run, check_suite, or workflow_run webhook event
     * 
     * These events contain CI/CD test results
     * 
     * @param array $payload
     * @return void
     */
    private function processCheckEvent(array $payload): void
    {
        // Extract test run data based on event type
        $testRunData = match ($this->event->event_type) {
            'check_run' => $payload['check_run'] ?? null,
            'check_suite' => $payload['check_suite'] ?? null,
            'workflow_run' => $payload['workflow_run'] ?? null,
            default => null,
        };
        
        if (!$testRunData) {
            throw new \Exception("Missing {$this->event->event_type} data in payload");
        }
        
        // Only process completed runs
        $status = $testRunData['status'] ?? $testRunData['conclusion'] ?? null;
        
        if ($status !== 'completed') {
            Log::debug('Skipping incomplete check event', [
                'event_id' => $this->event->id,
                'status' => $status,
            ]);
            return;
        }
        
        // Process test run (creates test_runs and test_results records)
        $testRun = app(ProcessTestRunAction::class)->execute(
            $this->event->repository_id,
            $testRunData,
            $this->event->event_type
        );
        
        // Queue flakiness detection if tests failed
        if ($testRun && $testRun->failed_tests > 0) {
            app(DetectFlakyTestsAction::class)->execute($testRun);
        }
        
        Log::info('Check event processed', [
            'event_id' => $this->event->id,
            'test_run_id' => $testRun?->id,
            'status' => $status,
        ]);
    }
    
    /**
     * Process status webhook event
     * 
     * Legacy GitHub status API (pre-checks API)
     * 
     * @param array $payload
     * @return void
     */
    private function processStatusEvent(array $payload): void
    {
        // Extract commit SHA and status
        $sha = $payload['sha'] ?? null;
        $state = $payload['state'] ?? null;
        
        if (!$sha || !$state) {
            throw new \Exception('Missing sha or state in status payload');
        }
        
        Log::debug('Processing status event', [
            'event_id' => $this->event->id,
            'sha' => $sha,
            'state' => $state,
        ]);
        
        // Update PRs with this commit SHA
        $this->updatePullRequestsByCommit($sha, $state);
    }
    
    /**
     * Process push webhook event
     * 
     * Updates default branch commits, triggers analysis
     * 
     * @param array $payload
     * @return void
     */
    private function processPushEvent(array $payload): void
    {
        $ref = $payload['ref'] ?? null;
        $commits = $payload['commits'] ?? [];
        
        Log::debug('Processing push event', [
            'event_id' => $this->event->id,
            'ref' => $ref,
            'commit_count' => count($commits),
        ]);
        
        // Queue repository sync if push to default branch
        // (implementation depends on repository sync requirements)
    }
    
    /**
     * Update pull requests by commit SHA
     * 
     * @param string $sha
     * @param string $state
     * @return void
     */
    private function updatePullRequestsByCommit(string $sha, string $state): void
    {
        // Find PRs with this commit
        $pullRequests = \App\Models\PullRequest::where('head_sha', $sha)->get();
        
        foreach ($pullRequests as $pr) {
            // Map GitHub state to our CI status
            $ciStatus = match ($state) {
                'success' => 'success',
                'failure', 'error' => 'failure',
                'pending' => 'pending',
                default => null,
            };
            
            if ($ciStatus) {
                $pr->update(['ci_status' => $ciStatus]);
                
                Log::debug('Updated PR CI status', [
                    'pr_id' => $pr->id,
                    'ci_status' => $ciStatus,
                    'sha' => $sha,
                ]);
            }
        }
    }
    
    /**
     * Determine queue priority
     * 
     * @return string
     */
    private function determineQueue(): string
    {
        $highPriority = [
            'pull_request' => ['opened', 'closed', 'reopened'],
        ];
        
        if (isset($highPriority[$this->event->event_type])) {
            $actions = $highPriority[$this->event->event_type];
            if (in_array($this->event->action, $actions, true)) {
                return 'high';
            }
        }
        
        return 'default';
    }
    
    /**
     * Handle job failure
     * 
     * Called when all retry attempts are exhausted
     * 
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('Webhook processing failed permanently', [
            'event_id' => $this->event->id,
            'delivery_id' => $this->event->delivery_id,
            'event_type' => $this->event->event_type,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
        
        // Update event status
        $this->event->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'retry_count' => $this->attempts(),
            'retry_after' => null, // No more retries
        ]);
        
        // TODO: Send alert to monitoring system (Better Stack, Slack, etc.)
    }
    
    /**
     * Get job tags for Horizon UI
     * 
     * @return array
     */
    public function tags(): array
    {
        return [
            'webhook',
            "event:{$this->event->event_type}",
            "delivery:{$this->event->delivery_id}",
            "repository:{$this->event->repository_id}",
        ];
    }
}