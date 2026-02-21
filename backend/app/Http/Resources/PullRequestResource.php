<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PullRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repository_id' => $this->repository_id,
            'author_id' => $this->author_id,
            'external_id' => $this->external_id,
            'number' => $this->number,
            'state' => $this->state,
            'title' => $this->title,
            'description' => $this->description,
            'head_branch' => $this->head_branch,
            'base_branch' => $this->base_branch,
            'head_sha' => $this->head_sha,
            'html_url' => $this->html_url,
            
            'additions' => $this->additions,
            'deletions' => $this->deletions,
            'changed_files' => $this->changed_files,
            'commits_count' => $this->commits_count,
            
            'ci_status' => $this->ci_status,
            'ci_checks_count' => $this->ci_checks_count,
            'ci_checks_passed' => $this->ci_checks_passed,
            'ci_checks_failed' => $this->ci_checks_failed,
            
            'test_coverage' => $this->test_coverage,
            'tests_total' => $this->tests_total,
            'tests_passed' => $this->tests_passed,
            'tests_failed' => $this->tests_failed,
            
            'cycle_time' => $this->cycle_time,
            'time_to_first_review' => $this->time_to_first_review,
            
            'is_draft' => $this->is_draft,
            'is_stale' => $this->is_stale,
            
            'labels' => $this->labels,
            
            'merged_at' => $this->merged_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            
            // Relations
            'author' => new UserResource($this->whenLoaded('author')),
            'repository' => new RepositoryResource($this->whenLoaded('repository')),
        ];
    }
}