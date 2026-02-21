<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repository_id' => $this->repository_id,
            'pull_request_id' => $this->pull_request_id,
            'ci_provider' => $this->ci_provider,
            'workflow_name' => $this->workflow_name,
            'branch' => $this->branch,
            'commit_sha' => $this->commit_sha,
            'status' => $this->status,
            
            'total_tests' => $this->total_tests,
            'passed_tests' => $this->passed_tests,
            'failed_tests' => $this->failed_tests,
            'skipped_tests' => $this->skipped_tests,
            'flaky_tests' => $this->flaky_tests,
            
            'line_coverage' => $this->line_coverage,
            'duration' => $this->duration,
            
            'run_url' => $this->run_url,
            
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            
            'pull_request' => new PullRequestResource($this->whenLoaded('pullRequest')),
        ];
    }
}