<?php

declare(strict_types=1);

namespace App\Actions\PullRequest;

use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Create or Update Pull Request Action
 * 
 * Creates or updates a PR from GitHub webhook payload.
 * Handles user creation/lookup and metric calculation.
 * 
 * @package App\Actions\PullRequest
 */
class CreateOrUpdatePullRequestAction
{
    /**
     * Execute the action
     * 
     * @param int $repositoryId Repository ID (from webhook_events)
     * @param array $prData Pull request data from GitHub payload
     * @return PullRequest
     * @throws \Exception
     */
    public function execute(int $repositoryId, array $prData): PullRequest
    {
        // Validate repository exists
        $repository = Repository::find($repositoryId);
        
        if (!$repository) {
            throw new \Exception("Repository not found: {$repositoryId}");
        }
        
        // Extract PR data from payload
        $externalId = $prData['id'] ?? null;
        $number = $prData['number'] ?? null;
        
        if (!$externalId || !$number) {
            throw new \Exception('Missing id or number in pull request data');
        }
        
        // Get or create PR author
        $authorData = $prData['user'] ?? null;
        $author = $authorData ? $this->getOrCreateUser($authorData) : null;
        
        // Parse PR state
        $state = $this->parseState($prData);
        
        // Calculate time metrics
        $timeMetrics = $this->calculateTimeMetrics($prData);
        
        // Use database transaction for consistency
        return DB::transaction(function () use (
            $repository,
            $externalId,
            $number,
            $author,
            $state,
            $prData,
            $timeMetrics
        ) {
            // Find existing PR or create new
            $pullRequest = PullRequest::where('repository_id', $repository->id)
                ->where('number', $number)
                ->first();
            
            $isNew = !$pullRequest;
            
            if (!$pullRequest) {
                $pullRequest = new PullRequest();
                $pullRequest->repository_id = $repository->id;
                $pullRequest->number = $number;
                $pullRequest->external_id = $externalId;
            }
            
            // Update PR fields
            $pullRequest->author_id = $author?->id;
            $pullRequest->state = $state;
            $pullRequest->title = $prData['title'] ?? '';
            $pullRequest->description = $prData['body'] ?? null;
            
            // Branch information
            $pullRequest->head_branch = $prData['head']['ref'] ?? '';
            $pullRequest->base_branch = $prData['base']['ref'] ?? '';
            $pullRequest->head_sha = $prData['head']['sha'] ?? '';
            $pullRequest->base_sha = $prData['base']['sha'] ?? '';
            
            // URLs
            $pullRequest->html_url = $prData['html_url'] ?? '';
            $pullRequest->diff_url = $prData['diff_url'] ?? null;
            
            // Statistics
            $pullRequest->additions = $prData['additions'] ?? 0;
            $pullRequest->deletions = $prData['deletions'] ?? 0;
            $pullRequest->changed_files = $prData['changed_files'] ?? 0;
            $pullRequest->commits_count = $prData['commits'] ?? 0;
            $pullRequest->comments_count = $prData['comments'] ?? 0;
            
            // Review status (will be updated by review webhooks)
            $pullRequest->review_comments_count = $prData['review_comments'] ?? 0;
            
            // Flags
            $pullRequest->is_draft = $prData['draft'] ?? false;
            $pullRequest->is_mergeable = $prData['mergeable'] ?? null;
            
            // Labels (JSON array)
            $labels = array_map(
                fn($label) => $label['name'] ?? $label,
                $prData['labels'] ?? []
            );
            $pullRequest->labels = $labels;
            
            // Assignees (JSON array of user IDs)
            $assigneeIds = array_map(
                fn($assignee) => $assignee['id'] ?? null,
                $prData['assignees'] ?? []
            );
            $pullRequest->assignees = array_filter($assigneeIds);
            
            // Requested reviewers (JSON array of user IDs)
            $reviewerIds = array_map(
                fn($reviewer) => $reviewer['id'] ?? null,
                $prData['requested_reviewers'] ?? []
            );
            $pullRequest->requested_reviewers = array_filter($reviewerIds);
            
            // Time metrics
            $pullRequest->cycle_time = $timeMetrics['cycle_time'];
            $pullRequest->time_to_first_review = $timeMetrics['time_to_first_review'];
            $pullRequest->time_to_approval = $timeMetrics['time_to_approval'];
            $pullRequest->time_to_merge = $timeMetrics['time_to_merge'];
            
            // Important timestamps
            $pullRequest->first_commit_at = $this->parseTimestamp($prData['created_at'] ?? null);
            $pullRequest->merged_at = $this->parseTimestamp($prData['merged_at'] ?? null);
            $pullRequest->closed_at = $this->parseTimestamp($prData['closed_at'] ?? null);
            $pullRequest->last_activity_at = $this->parseTimestamp($prData['updated_at'] ?? null);
            
            // Calculate staleness (no activity for 14+ days)
            if ($state === 'open' && $pullRequest->last_activity_at) {
                $daysSinceActivity = now()->diffInDays($pullRequest->last_activity_at);
                $pullRequest->is_stale = $daysSinceActivity >= 14;
            } else {
                $pullRequest->is_stale = false;
            }
            
            // Metadata (store full payload for debugging)
            $pullRequest->metadata = [
                'milestone' => $prData['milestone'] ?? null,
                'locked' => $prData['locked'] ?? false,
                'auto_merge' => $prData['auto_merge'] ?? null,
            ];
            
            $pullRequest->save();
            
            Log::info($isNew ? 'Pull request created' : 'Pull request updated', [
                'pr_id' => $pullRequest->id,
                'repository_id' => $repository->id,
                'pr_number' => $number,
                'state' => $state,
                'is_new' => $isNew,
            ]);
            
            return $pullRequest;
        });
    }
    
    /**
     * Get or create user from GitHub data
     * 
     * @param array $userData
     * @return User
     */
    private function getOrCreateUser(array $userData): User
    {
        $externalId = $userData['id'] ?? null;
        $username = $userData['login'] ?? null;
        
        if (!$externalId || !$username) {
            throw new \Exception('Missing id or login in user data');
        }
        
        // Find by external_id first (most reliable)
        $user = User::where('external_id', $externalId)->first();
        
        if ($user) {
            // Update user info if changed
            $user->update([
                'username' => $username,
                'name' => $userData['name'] ?? $user->name,
                'avatar_url' => $userData['avatar_url'] ?? $user->avatar_url,
            ]);
            
            return $user;
        }
        
        // Create new user
        return User::create([
            'external_id' => $externalId,
            'provider' => 'github',
            'username' => $username,
            'name' => $userData['name'] ?? $username,
            'email' => $userData['email'] ?? "{$username}@users.noreply.github.com",
            'avatar_url' => $userData['avatar_url'] ?? null,
            'bio' => $userData['bio'] ?? null,
            'location' => $userData['location'] ?? null,
            'company' => $userData['company'] ?? null,
            'website_url' => $userData['blog'] ?? null,
            'role' => 'viewer', // Default role for webhook-created users
            'is_active' => true,
        ]);
    }
    
    /**
     * Parse PR state from GitHub payload
     * 
     * @param array $prData
     * @return string 'open', 'closed', or 'merged'
     */
    private function parseState(array $prData): string
    {
        $state = $prData['state'] ?? 'open';
        $merged = $prData['merged'] ?? false;
        
        if ($merged) {
            return 'merged';
        }
        
        return $state; // 'open' or 'closed'
    }
    
    /**
     * Calculate time metrics from PR data
     * 
     * @param array $prData
     * @return array
     */
    private function calculateTimeMetrics(array $prData): array
    {
        $createdAt = $this->parseTimestamp($prData['created_at'] ?? null);
        $mergedAt = $this->parseTimestamp($prData['merged_at'] ?? null);
        
        $cycleTime = null;
        if ($createdAt && $mergedAt) {
            $cycleTime = $createdAt->diffInSeconds($mergedAt);
        }
        
        // Other time metrics will be calculated from review webhooks
        // For now, set to null
        return [
            'cycle_time' => $cycleTime,
            'time_to_first_review' => null,
            'time_to_approval' => null,
            'time_to_merge' => null,
        ];
    }
    
    /**
     * Parse ISO 8601 timestamp to Carbon instance
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
            Log::warning('Failed to parse timestamp', [
                'timestamp' => $timestamp,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}